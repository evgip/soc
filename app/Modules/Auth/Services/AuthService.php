<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use W3a\Core\Http\Session;
use W3a\Core\Support\Logger;
use W3a\Core\Support\Audit;
use W3a\Core\Foundation\Config;
use W3a\Core\Http\Request;
use W3a\Core\Support\Lang;

use App\Modules\Users\Models\User;
use App\Modules\Auth\Models\RememberToken;
use App\Modules\Auth\Models\EmailActivation;
use App\Modules\Auth\Models\AuthAttempt;
use App\Modules\Mail\Core\Mailer;

use App\Modules\Auth\Exceptions\AuthBlockedException;
use App\Modules\Auth\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Exceptions\AccountNotActiveException;
use App\Modules\Auth\Exceptions\RegistrationFailedException;
use App\Modules\Auth\Exceptions\InvalidTokenException;

/**
 * Сервис аутентификации и управления сессиями пользователей.
 * 
 * Отвечает за бизнес-логику входа, регистрации, активации и защиты от брутфорса.
 * НЕ управляет UI (flash-сообщениями), а выбрасывает исключения при ошибках.
 */
class AuthService
{
    private User $userModel;
    private RememberToken $rememberTokenModel;
    private EmailActivation $emailActivationModel;
    private AuthAttempt $authAttemptModel;
    private Logger $logger;
    private Session $session;
    private Audit $audit;
    private Mailer $mailer;
    private Config $config;
    private Request $request;

    private const MAX_ATTEMPTS_IP = 5;
    private const MAX_ATTEMPTS_EMAIL = 10;
    private const LOCKOUT_MINUTES = 15;
    
    // Хэш-заглушка для защиты от атак по времени (timing attacks)
    private const DUMMY_HASH = '$2y$10$DummyHashForTimingProtection00000000000000000000';

    private const COOKIE_NAME = 'remember_me';
    private const COOKIE_DAYS = 30;

    public function __construct(
        User $userModel,
        RememberToken $rememberTokenModel,
        EmailActivation $emailActivationModel,
        AuthAttempt $authAttemptModel,
        Logger $logger,
        Session $session,
        Audit $audit,
        Mailer $mailer,
        Config $config,
        Request $request
    ) {
        $this->userModel = $userModel;
        $this->rememberTokenModel = $rememberTokenModel;
        $this->emailActivationModel = $emailActivationModel;
        $this->authAttemptModel = $authAttemptModel;
        $this->logger = $logger;
        $this->session = $session;
        $this->audit = $audit;
        $this->mailer = $mailer;
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * Аутентифицирует пользователя по email и паролю.
     *
     * @throws AuthBlockedException Если превышен лимит попыток
     * @throws InvalidCredentialsException Если email или пароль неверны
     * @throws AccountNotActiveException Если аккаунт не активирован
     */
    public function authenticate(string $email, string $password): array
    {
        $ip = $this->request->getIp();
        $blockType = $this->checkBlockStatus($ip, $email);
        
        if ($blockType !== null) {
            throw new AuthBlockedException($this->getBlockMessage($blockType));
        }

        $user = $this->userModel->findBy('email', $email);

        // Проверка пароля (или заглушки, если пользователя нет, для защиты от timing-атак)
        if (!$user || !password_verify($password, $user['password'])) {
            if (!$user) {
                password_verify($password, self::DUMMY_HASH);
            }
            $this->logFailedAttempt($ip, $email);
            throw new InvalidCredentialsException('Неверный email или пароль.');
        }

        if ((int)$user['is_active'] !== 1) {
            $this->logFailedAttempt($ip, $email, 'inactive_account');
            throw new AccountNotActiveException('Аккаунт не активирован. Проверьте вашу почту.');
        }

        // Запрет входа для забаненных пользователей
        if ($this->userModel->isBanned((int)$user['id'])) {
            $this->logFailedAttempt($ip, $email, 'banned_account');
            // Можно использовать существующее исключение или создать новое BannedAccountException
            throw new AccountNotActiveException('Ваш аккаунт заблокирован администрацией.');
        }

        // Успешный вход: очищаем историю неудачных попыток
        $this->clearFailedAttempts($ip, $email);
        
        return $user;
    }

    /**
     * Проверяет, заблокирован ли IP или email из-за частых попыток входа.
     */
    private function checkBlockStatus(string $ip, string $email): ?string
    {
        if ($this->isIpBlocked($ip)) {
            return 'ip';
        }
        if ($email !== '' && $this->isEmailBlocked($email)) {
            return 'email';
        }
        return null;
    }

    /**
     * Возвращает понятное сообщение о блокировке.
     */
    private function getBlockMessage(string $type): string
    {
        return match ($type) {
            'ip' => 'Слишком много попыток с вашего IP. Подождите ' . self::LOCKOUT_MINUTES . ' минут.',
            'email' => 'Слишком много попыток входа для этого аккаунта. Подождите ' . self::LOCKOUT_MINUTES . ' минут.',
            default => 'Вход временно недоступен.',
        };
    }

    /**
     * Проверяет количество неудачных попыток по IP.
     */
    private function isIpBlocked(string $ip): bool
    {
        $count = $this->authAttemptModel->countFailedByIp($ip, self::LOCKOUT_MINUTES);
        return $count >= self::MAX_ATTEMPTS_IP;
    }

    /**
     * Проверяет количество неудачных попыток по Email.
     */
    private function isEmailBlocked(string $email): bool
    {
        $count = $this->authAttemptModel->countFailedByEmail($email, self::LOCKOUT_MINUTES);
        return $count >= self::MAX_ATTEMPTS_EMAIL;
    }

    /**
     * Логирует неудачную попытку входа в систему аудита.
     */
    private function logFailedAttempt(string $ip, string $email, string $reason = 'invalid_credentials'): void
    {
        $this->audit->log(
            'auth.login_failed',
            'Неудачная попытка входа',
            'auth',
            [
                'email' => $email,
                'ip' => $ip,
                'reason' => $reason,
                'user_agent' => $this->request->header('HTTP_USER_AGENT', 'Unknown'),
                'timestamp' => date('c'),
            ]
        );
    }

    /**
     * Очищает историю неудачных попыток для IP и Email после успешного входа.
     */
    private function clearFailedAttempts(string $ip, string $email): void
    {
        $this->authAttemptModel->clearForIpAndEmail($ip, $email);
    }

    /**
     * Возвращает оставшееся время блокировки в секундах.
     */
    public function getRemainingLockoutTime(string $ip): int
    {
        $lastAttempt = $this->authAttemptModel->getLastFailedAttemptTime($ip, self::LOCKOUT_MINUTES);

        if (!$lastAttempt) {
            return 0;
        }

        $lockoutUntil = strtotime($lastAttempt) + (self::LOCKOUT_MINUTES * 60);
        return max(0, $lockoutUntil - time());
    }

    /**
     * Регистрирует нового пользователя и отправляет письмо активации.
     *
     * @throws RegistrationFailedException Если не удалось создать пользователя
     */
    public function register(string $username, string $email, string $password): int
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $userId = $this->userModel->create([
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'is_active' => 0,
            'role' => 'user',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$userId) {
            throw new RegistrationFailedException('Не удалось создать пользователя в базе данных.');
        }

        $token = bin2hex(random_bytes(32));
        $this->emailActivationModel->createToken($userId, $token);
        $this->sendActivationEmail($email, $username, $token);

        return $userId;
    }

    /**
     * Активирует аккаунт по токену из письма.
     *
     * @throws InvalidTokenException Если токен недействителен или истёк
     */
    public function activateAccount(string $token): bool
    {
        if (empty($token)) {
            throw new InvalidTokenException('Недействительная ссылка активации.');
        }

        $tokenData = $this->emailActivationModel->findByToken($token);

        if (!$tokenData) {
            throw new InvalidTokenException('Ссылка активации не найдена или уже использована.');
        }

        $createdAt = strtotime($tokenData['created_at']);
        if ((time() - $createdAt) > 86400) { // 24 часа
            $this->emailActivationModel->deleteByToken($token);
            throw new InvalidTokenException('Срок действия ссылки активации истёк.');
        }

        $user = $this->userModel->find((int) $tokenData['user_id']);

        if (!$user) {
            $this->emailActivationModel->deleteByToken($token);
            throw new InvalidTokenException('Пользователь не найден.');
        }

        $success = $this->userModel->update((int) $user['id'], [
            'is_active' => 1,
        ]);

        if ($success) {
            $this->emailActivationModel->deleteByToken($token);

            $this->audit->log('auth.account_activated', 'Аккаунт активирован', 'auth', [
                'user_id' => $user['id'],
                'email' => $user['email'],
            ]);
        }

        return $success;
    }

    /**
     * Отправляет письмо с ссылкой для активации аккаунта.
     */
    private function sendActivationEmail(string $email, string $username, string $token): void
    {
        $baseUrl = $this->config->getString('config.app.url', '');
        
        if (empty($baseUrl)) {
            $scheme = $this->request->isSecure() ? 'https' : 'http';
            $host = $this->request->header('HTTP_HOST', 'localhost');
            $baseUrl = $scheme . '://' . $host;
        }

        $activationUrl = rtrim($baseUrl, '/') . '/register/activate/' . $token;
        $siteName = $this->config->getString('config.app.name', $baseUrl);

        $subject = sprintf(
            Lang::get('email_activation_subject'),
            htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8')
        );

        $body = sprintf(
            Lang::get('email_activation_body'),
            htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($activationUrl, ENT_QUOTES, 'UTF-8')
        );

        $this->audit->log('auth.activation_email', 'Отправка письма активации', 'auth', [
            'to' => $email,
            'subject' => $subject,
        ]);

        $result = $this->mailer->send($email, $subject, $body);

        if (!$result) {
            $this->audit->log('auth.activation_email_failed', 'Не удалось отправить письмо активации', 'auth', [
                'email' => $email,
            ]);
        }
    }

    /**
     * Создает сессию для пользователя и опционально устанавливает cookie "Запомнить меня".
     */
    public function createSession(array $user, bool $remember = false): void
    {
        // Защита от фиксации сессии (Session Fixation)
        $this->session->regenerate(true);

        $this->session->set('user_id', $user['id']);
        $this->session->set('user_name', $user['username'] ?? $user['name']);
        $this->session->set('user_role', $user['role'] ?? 'user');
        $this->session->set('last_activity_time', time());

        $profile = $this->userModel->getProfile((int) $user['id']);
        $this->session->set('user_avatar', $profile['avatar'] ?? null);

        if ($remember) {
            $this->createRememberCookie($user['id']);
        }

        $this->audit->log('auth.login_success', 'Пользователь вошел в систему', 'auth');
    }

    /**
     * Создает безопасный токен "Запомнить меня" в cookie и БД.
     */
    private function createRememberCookie(int $userId): void
    {
        $tokenData = $this->rememberTokenModel->createToken(
            $userId,
            self::COOKIE_DAYS,
            $this->request->header('HTTP_USER_AGENT', 'Unknown'),
            $this->request->getIp()
        );

        $expiry = time() + (self::COOKIE_DAYS * 86400);

        setcookie(
            self::COOKIE_NAME,
            $tokenData['token'],
            [
                'expires' => $expiry,
                'path' => '/',
                'domain' => '',
                'secure' => $this->request->isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Пытается восстановить сессию пользователя по cookie "Запомнить меня".
     */
    public function attemptRememberLogin(): bool
    {
        if (!$this->request->hasCookie(self::COOKIE_NAME)) {
            return false;
        }

        $token = $this->request->cookie(self::COOKIE_NAME);
        $parts = explode(':', $token, 2);
        
        if (count($parts) !== 2) {
            $this->clearRememberCookie();
            return false;
        }

        [$selector, $validator] = $parts;
        $record = $this->rememberTokenModel->validateToken($selector, $validator);

        if (!$record) {
            $this->clearRememberCookie();
            return false;
        }

        $user = $this->userModel->find((int) $record['user_id']);

        if (!$user) {
            $this->clearRememberCookie();
            return false;
        }

        if ($this->userModel->isBanned((int) $user['id'])) {
            $this->clearRememberCookie();
            $this->audit->log(
                'auth.remember_blocked', 
                'Попытка входа по токену забаненного пользователя', 
                'auth'
            );
            return false;
        }

        $this->createSession($user, false);
        $this->createRememberCookie($user['id']); // Обновляем токен для безопасности

        $this->audit->log('auth.remember_success', 'Восстановление сессии по токену', 'auth');

        return true;
    }

    /**
     * Безопасно удаляет токен из БД и очищает cookie.
     */
    private function clearRememberCookie(): void
    {
        if ($this->request->hasCookie(self::COOKIE_NAME)) {
            $token = $this->request->cookie(self::COOKIE_NAME);
            $parts = explode(':', $token, 2);
            
            if (count($parts) === 2) {
                $this->rememberTokenModel->deleteBySelector($parts[0]);
            }

            setcookie(
                self::COOKIE_NAME,
                '',
                [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $this->request->isSecure(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        }
    }

    /**
     * Корректно завершает сессию пользователя, сохраняя flash-сообщения.
     */
    public function logout(): void
    {
        $this->clearRememberCookie();

        if ($this->session->has('user_id')) {
            try {
                $this->audit->log('auth.logout', 'Пользователь вышел из системы', 'auth');
            } catch (\Throwable $e) {
                // Игнорируем ошибки логирования при выходе, чтобы не ломать процесс
            }
        }

        // Сохраняем flash-данные перед уничтожением сессии
        $flashData = $this->session->get('flash');

        $this->session->clear();
        $this->session->destroy();
        $this->session->start();
        
        if ($flashData) {
            $this->session->set('flash', $flashData);
        }
    }
}