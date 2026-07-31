<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use W3a\Core\Auth\AuthService as BaseAuthService;
use W3a\Core\Support\Lang;
use W3a\Core\Http\Session;
use W3a\Core\Foundation\Config;
use W3a\Core\Http\Request;
use W3a\Core\Support\Audit;
use W3a\Core\Support\Logger;

use App\Modules\Users\Models\User;
use W3a\Core\Auth\Models\RememberToken;
use W3a\Core\Auth\Models\EmailActivation;
use App\Modules\Mail\Core\Mailer;

/**
 * Расширение базового AuthService для специфики приложения.
 * 
 * Переопределяет только то, что уникально для soc.local:
 * - Отправка писем активации через Mailer приложения
 * - Проверка банов через таблицу user_bans
 * - Загрузка аватара из user_profiles при создании сессии
 */
class AppAuthService extends BaseAuthService
{
    private Mailer $mailer;

    public function __construct(
        User $userModel,
        RememberToken $rememberTokenModel,
        EmailActivation $emailActivationModel,
        Logger $logger,
        Session $session,
        Audit $audit,
        Config $config,
        Request $request
    ) {
        parent::__construct(
            $userModel,
            $rememberTokenModel,
            $emailActivationModel,
            $logger,
            $session,
            $audit,
            $config,
            $request
        );

        $this->mailer = container(Mailer::class);
    }

    // ═══════════════════════════════════════════════════════════
    //  ПЕРЕОПРЕДЕЛЕНИЯ
    // ═══════════════════════════════════════════════════════════

    /**
     * Проверка бана через таблицу user_bans (специфика приложения).
     */
    protected function isUserBanned(int $userId): bool
    {
        /** @var User $userModel */
        $userModel = $this->userModel;
        return $userModel->isBanned($userId);
    }

    /**
     * Отправка письма активации через Mailer приложения.
     */
    protected function sendActivationEmail(string $email, string $username, string $token): void
    {
        // Формируем базовый URL сайта
        $baseUrl = $this->config->getString('app.url', '');
        if (empty($baseUrl)) {
            $scheme  = $this->request->isSecure() ? 'https' : 'http';
            $host    = $this->request->header('HTTP_HOST', 'localhost');
            $baseUrl = $scheme . '://' . $host;
        }

        $activationUrl = rtrim($baseUrl, '/') . '/register/activate/' . $token;
        $siteName      = $this->config->getString('app.name', $baseUrl);

        // Локализованные темы и тело письма
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
            'to'      => $email,
            'subject' => $subject,
        ]);

        $this->mailer->send($email, $subject, $body);
    }

    /**
     * Расширяем создание сессии: добавляем аватар из user_profiles.
     */
    public function createSession(array $user, bool $remember = false): void
    {
        parent::createSession($user, $remember);

        /** @var User $userModel */
        $userModel = $this->userModel;
        $profile   = $userModel->getProfile((int)$user['id']);
        $this->session->set('user_avatar', $profile['avatar'] ?? null);
    }
}