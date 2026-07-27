<?php

declare(strict_types=1);

namespace App\Modules\Invitations\Controllers;

use App\BaseController;
use W3a\Core\Validator;
use W3a\Core\Lang;
use App\Modules\Invitations\Models\Invitation;
use App\Modules\Invitations\Models\InvitationRequest;
use App\Modules\Users\Models\User;
use App\Modules\Mail\Core\Mailer;

/**
 * Контроллер системы приглашений (invitations).
 * 
 * Обрабатывает:
 * - Управление приглашениями для авторизованных пользователей
 * - Создание и отзыв приглашений с учётом лимитов и кармы
 * - Регистрацию новых пользователей по приглашению
 * - Публичные запросы на приглашение для незарегистрированных
 * 
 * Система может быть полностью отключена через конфиг
 * (invitations.config.invitations_enabled).
 * 
 * Маршруты управления (index, create, revoke) защищены middleware auth.
 * Маршруты регистрации (showInviteRegistration, registerWithInvite) — публичные.
 * Маршруты запроса приглашения (showRequestForm, submitRequest) — публичные.
 */
class InvitationsController extends BaseController
{
    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Получить Mailer из контейнера
     */
    private function mailer(): Mailer
    {
        return $this->container->get(Mailer::class);
    }

    /**
     * Получить Validator из контейнера
     */
    private function validator(): Validator
    {
        return $this->container->get(Validator::class);
    }

    /**
     * Проверить, включена ли система инвайтов в конфиге
     */
    private function isInvitationsEnabled(): bool
    {
        return (bool) config('invitations.config.invitations_enabled');
    }

    /**
     * Проверить, достаточно ли кармы у пользователя для создания приглашений
     */
    private function hasEnoughKarma(int $userId): bool
    {
        $minKarma = (int) config('invitations.config.min_karma_for_invitation');
        $userKarma = $this->service(User::class)->getUserKarma($userId);
        return $userKarma >= $minKarma;
    }

    // =========================================================================
    // УПРАВЛЕНИЕ ПРИГЛАШЕНИЯМИ (для авторизованных пользователей)
    // =========================================================================

    /**
     * Главная страница управления приглашениями (GET /invitations).
     * 
     * Показывает список приглашений пользователя, количество активных
     * и доступный лимит. Также проверяет, достаточно ли кармы
     * для создания новых приглашений.
     */
    public function index(): void
    {
        if (!$this->isInvitationsEnabled()) {
            $this->redirectWithMessage('/', 'Система приглашений отключена.', 'error');
            return;
        }

        $userContext = $this->getUserContext();
        $invitationModel = $this->service(Invitation::class);

        $invitations = $invitationModel->getUserInvitations($userContext['id']);
        $activeCount = $invitationModel->countActiveInvitations($userContext['id']);
        $maxInvitations = (int) config('invitations.config.max_invitations_per_user');

        $this->render('index', [
            'title' => 'Управление приглашениями',
            'invitations' => $invitations,
            'activeCount' => $activeCount,
            'maxInvitations' => $maxInvitations,
            'hasEnoughKarma' => $this->hasEnoughKarma($userContext['id']),
            'minKarma' => (int) config('invitations.config.min_karma_for_invitation'),
            'request' => $this->request
        ]);
    }

    /**
     * Создание нового приглашения (POST /invitations/create).
     * 
     * Проверяет:
     * - Включена ли система приглашений
     * - Достаточно ли кармы у пользователя
     * - Не превышен ли лимит активных приглашений
     * - Валидность email (если указан)
     * 
     * Если указан email, отправляет письмо с приглашением.
     */
    public function create(): void
    {
        $this->request->validateCsrf();

        if (!$this->isInvitationsEnabled()) {
            $this->redirectWithMessage('/', 'Система приглашений отключена.', 'error');
            return;
        }

        $userContext = $this->getUserContext();

        if (!$this->hasEnoughKarma($userContext['id'])) {
            $this->redirectWithMessage(
                route('invitations.index'),
                'Недостаточно кармы для создания приглашений.',
                'error'
            );
            return;
        }

        $invitationModel = $this->service(Invitation::class);

        $activeCount = $invitationModel->countActiveInvitations($userContext['id']);
        $maxInvitations = (int) config('invitations.config.max_invitations_per_user');

        if ($activeCount >= $maxInvitations) {
            $this->redirectWithMessage(
                route('invitations.index'),
                "Вы достигли лимита активных приглашений ({$maxInvitations}).",
                'error'
            );
            return;
        }

        // Валидация email, если он указан
        $email = trim((string) $this->request->getParams('email'));
        if (!empty($email)) {
            $this->validator()->validate(['email' => $email], [
                'email' => 'required|email'
            ]);

            if (!$this->validator()->isValid()) {
                $this->redirectWithMessage(
                    route('invitations.index'),
                    'Некорректный email адрес.',
                    'error'
                );
                return;
            }
        }

        $expiresDays = (int) config('invitations.config.invitation_expires_days', 7);
        $invitationId = $invitationModel->createInvitation($userContext['id'], $email ?: null, $expiresDays);

        if ($invitationId) {
            $invitation = $invitationModel->find($invitationId);

            if (!empty($email)) {
                $this->sendInvitationEmail($email, $invitation);
            }

            $this->redirectWithMessage(route('invitations.index'), 'Приглашение успешно создано!', 'success');
            return;
        }

        $this->redirectWithMessage(route('invitations.index'), 'Ошибка создания приглашения.', 'error');
    }

    /**
     * Отзыв приглашения (POST /invitations/revoke/{id}).
     * 
     * Приглашение может отозвать только его создатель.
     */
    public function revoke(int $id): void
    {
        $this->request->validateCsrf();

        $userContext = $this->getUserContext();

        if ($this->service(Invitation::class)->revokeInvitation($id, $userContext['id'])) {
            $this->redirectWithMessage(route('invitations.index'), 'Приглашение отозвано.', 'success');
            return;
        }

        $this->redirectWithMessage(route('invitations.index'), 'Не удалось отозвать приглашение.', 'error');
    }

    // =========================================================================
    // РЕГИСТРАЦИЯ ПО ПРИГЛАШЕНИЮ (публичные маршруты)
    // =========================================================================

    /**
     * Страница регистрации по приглашению (GET /register/invite/{code}).
     * 
     * Проверяет валидность кода приглашения и показывает форму регистрации.
     */
    public function showInviteRegistration(string $code): void
    {
        if (!$this->isInvitationsEnabled()) {
            $this->redirectWithMessage('/', 'Система приглашений отключена.', 'error');
            return;
        }

        $invitationModel = $this->service(Invitation::class);
        $invitation = $invitationModel->findByCode($code);

        if (!$invitation || !$invitationModel->isValid($invitation)) {
            $this->redirectWithMessage('/', 'Приглашение недействительно или истек срок действия.', 'error');
            return;
        }

        $this->render('register_invite', [
            'title' => 'Регистрация по приглашению',
            'code' => $code,
            'invitation' => $invitation,
            'request' => $this->request
        ]);
    }

    /**
     * Обработка регистрации по приглашению (POST /register/invite/{code}).
     * 
     * Валидирует данные формы, проверяет уникальность username и email,
     * создаёт нового пользователя и активирует приглашение.
     */
    public function registerWithInvite(string $code): void
    {
        if (!$this->isInvitationsEnabled()) {
            $this->redirectWithMessage('/', 'Система приглашений отключена.', 'error');
            return;
        }

        $this->request->validateCsrf();

        $invitationModel = $this->service(Invitation::class);
        $invitation = $invitationModel->findByCode($code);

        if (!$invitation || !$invitationModel->isValid($invitation)) {
            $this->redirectWithMessage('/', 'Приглашение недействительно или истек срок действия.', 'error');
            return;
        }

        // Валидация формы регистрации
        $this->validator()->validate([
            'username' => $this->request->getParams('username'),
            'email' => $this->request->getParams('email'),
            'password' => $this->request->getParams('password'),
            'password_confirm' => $this->request->getParams('password_confirm')
        ], [
            'username' => 'required|min:3|max:50',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'password_confirm' => 'required|match:password'
        ]);

        if (!$this->validator()->isValid()) {
            $this->redirectWithMessage(
                '/register/invite/' . $code,
                $this->formatValidationErrors(),
                'error'
            );
            return;
        }

        $userModel = $this->service(User::class);
        $username = (string) $this->request->getParams('username');
        $email = (string) $this->request->getParams('email');

        if ($userModel->findBy('username', $username)) {
            $this->redirectWithMessage('/register/invite/' . $code, 'Имя пользователя уже занято.', 'error');
            return;
        }

        if ($userModel->findBy('email', $email)) {
            $this->redirectWithMessage('/register/invite/' . $code, 'Email уже зарегистрирован.', 'error');
            return;
        }

        $newUserId = $userModel->create([
            'username' => $username,
            'email' => $email,
            'password' => password_hash((string) $this->request->getParams('password'), PASSWORD_BCRYPT),
            'role' => 'user',
            'is_active' => 1
        ]);

        if ($newUserId > 0) {
            $invitationModel->acceptInvitation($code, $newUserId);

            $this->redirectWithMessage(route('auth.login'), 'Регистрация успешна! Добро пожаловать!', 'success');
            return;
        }

        $this->redirectWithMessage('/register/invite/' . $code, 'Ошибка регистрации.', 'error');
    }

    // =========================================================================
    // ЗАПРОС ПРИГЛАШЕНИЯ (публичные маршруты)
    // =========================================================================

    /**
     * Форма запроса приглашения (GET /invite/request).
     * 
     * Доступна для незарегистрированных пользователей,
     * которые хотят получить приглашение в систему.
     */
    public function showRequestForm(): void
    {
        if (!$this->isInvitationsEnabled()) {
            $this->redirectWithMessage('/', 'Система приглашений отключена.', 'error');
            return;
        }

        $this->render('request', [
            'title' => 'Запрос приглашения',
            'request' => $this->request
        ]);
    }

    /**
     * Обработка запроса приглашения (POST /invite/request).
     * 
     * Валидирует email и причину запроса, проверяет:
     * - Отсутствие уже отправленного запроса
     * - Отсутствие пользователя с таким email в системе
     * 
     * Сохраняет запрос с IP-адресом для рассмотрения модераторами.
     */
    public function submitRequest(): void
    {
        if (!$this->isInvitationsEnabled()) {
            $this->redirectWithMessage('/', 'Система приглашений отключена.', 'error');
            return;
        }

        $this->request->validateCsrf();

        $email = trim((string) $this->request->getParams('email'));
        $reason = trim((string) $this->request->getParams('reason'));

        $this->validator()->validate([
            'email' => $email,
            'reason' => $reason
        ], [
            'email' => 'required|email',
            'reason' => 'required|min:10'
        ]);

        if (!$this->validator()->isValid()) {
            $this->redirectWithMessage('/invite/request', $this->formatValidationErrors(), 'error');
            return;
        }

        $requestModel = $this->service(InvitationRequest::class);

        if ($requestModel->hasPendingRequest($email)) {
            $this->redirectWithMessage('/invite/request', 'Вы уже отправили запрос. Ожидайте рассмотрения.', 'error');
            return;
        }

        $userModel = $this->service(User::class);
        if ($userModel->findBy('email', $email)) {
            $this->redirectWithMessage('/invite/request', 'Этот email уже зарегистрирован.', 'error');
            return;
        }

        // Используем метод Request вместо прямого обращения к $_SERVER
        $requestModel->createRequest($email, $reason, $this->request->getIp());

        $this->redirectWithMessage('/', 'Ваш запрос отправлен! Мы рассмотрим его в ближайшее время.', 'success');
    }

    // =========================================================================
    // ОТПРАВКА EMAIL-УВЕДОМЛЕНИЙ
    // =========================================================================

    /**
     * Отправка email с приглашением указанному адресу.
     * 
     * Использует языковые шаблоны для формирования темы и тела письма.
     */
    private function sendInvitationEmail(string $email, array $invitation): void
    {
        $siteName = app_name();
        $inviteUrl = route('home') . 'register/invite/' . $invitation['code'];
        $expiresAt = dt($invitation['expires_at']);

        $subject = Lang::format('email_invitation_subject', [e($siteName)]);
        $htmlBody = Lang::format('email_invitation_body', [
            e($siteName),
            e($inviteUrl),
            e($expiresAt)
        ]);

        $this->mailer()->send($email, $subject, $htmlBody);
    }

    /**
     * Отправка email об одобрении запроса на приглашение.
     * 
     * Содержит код приглашения и ссылку на регистрацию.
     */
    private function sendApprovedEmail(string $email, string $inviteCode, string $expiresAt): void
    {
        $siteName = app_name();
        $inviteUrl = route('home') . 'register/invite/' . $inviteCode;

        $subject = Lang::format('email_invitation_request_approved_subject', [e($siteName)]);
        $htmlBody = Lang::format('email_invitation_request_approved_body', [
            e($siteName),
            e($inviteUrl),
            e($expiresAt)
        ]);

        $this->mailer()->send($email, $subject, $htmlBody);
    }

    /**
     * Отправка email об отклонении запроса на приглашение.
     */
    private function sendRejectedEmail(string $email): void
    {
        $siteName = app_name();

        $subject = Lang::format('email_invitation_request_rejected_subject', [e($siteName)]);
        $htmlBody = Lang::format('email_invitation_request_rejected_body', [
            e($siteName)
        ]);

        $this->mailer()->send($email, $subject, $htmlBody);
    }

    // =========================================================================
    // УТИЛИТЫ
    // =========================================================================

    /**
     * Форматировать ошибки валидации в одну строку.
     * 
     * Объединяет все ошибки из всех полей в одну строку,
     * разделённую <br> для отображения в flash-сообщении.
     */
    private function formatValidationErrors(): string
    {
        $errors = $this->validator()->getErrors();
        $errorMessages = [];

        foreach ($errors as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $errorMessages[] = $error;
            }
        }

        return implode('<br>', $errorMessages);
    }
}
