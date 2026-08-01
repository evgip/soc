<?php

declare(strict_types=1);

namespace App\Modules\Invitations\Controllers;

use App\BaseController;
use W3a\Core\Http\Response;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Support\Validator;
use W3a\Core\Support\Lang;
use W3a\Core\Support\MessageBag;

use App\Modules\Invitations\Models\Invitation;
use App\Modules\Invitations\Models\InvitationRequest;
use App\Modules\Users\Models\User;
use App\Modules\Mail\Core\Mailer;

/**
 * Контроллер системы приглашений (invitations).
 */
class InvitationsController extends BaseController
{
    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    private function mailer(): Mailer
    {
        return $this->container->get(Mailer::class);
    }

    private function validator(): Validator
    {
        return $this->container->get(Validator::class);
    }

    private function isInvitationsEnabled(): bool
    {
        return (bool) config('invitations.config.invitations_enabled');
    }

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
     */
    public function index(): Response
    {
        if (!$this->isInvitationsEnabled()) {
            MessageBag::flashMessage('error', 'Система приглашений отключена.');
            return $this->redirect('/');
        }

        $userContext = $this->getUserContext();
        $invitationModel = $this->service(Invitation::class);

        $invitations = $invitationModel->getUserInvitations($userContext['id']);
        $activeCount = $invitationModel->countActiveInvitations($userContext['id']);
        $maxInvitations = (int) config('invitations.config.max_invitations_per_user');

        return $this->render('index', [
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
     */
    public function create(): RedirectResponse
    {
        $this->request->validateCsrf();

        if (!$this->isInvitationsEnabled()) {
            MessageBag::flashMessage('error', 'Система приглашений отключена.');
            return $this->redirect('/');
        }

        $userContext = $this->getUserContext();

        if (!$this->hasEnoughKarma($userContext['id'])) {
            MessageBag::flashMessage('error', 'Недостаточно кармы для создания приглашений.');
            return $this->redirect(route('invitations.index'));
        }

        $invitationModel = $this->service(Invitation::class);
        $activeCount = $invitationModel->countActiveInvitations($userContext['id']);
        $maxInvitations = (int) config('invitations.config.max_invitations_per_user');

        if ($activeCount >= $maxInvitations) {
            MessageBag::flashMessage('error', "Вы достигли лимита активных приглашений ({$maxInvitations}).");
            return $this->redirect(route('invitations.index'));
        }

        // Валидация email, если он указан
        $email = trim((string) $this->request->getParams('email'));
        if (!empty($email)) {
            $this->validator()->validate(['email' => $email], ['email' => 'required|email']);

            if (!$this->validator()->isValid()) {
                MessageBag::flashMessage('error', 'Некорректный email адрес.');
                return $this->redirect(route('invitations.index'));
            }
        }

        $expiresDays = (int) config('invitations.config.invitation_expires_days', 7);
        $invitationId = $invitationModel->createInvitation($userContext['id'], $email ?: null, $expiresDays);

        if ($invitationId) {
            $invitation = $invitationModel->find($invitationId);

            if (!empty($email)) {
                $this->sendInvitationEmail($email, $invitation);
            }

            MessageBag::flashMessage('success', 'Приглашение успешно создано!');
            return $this->redirect(route('invitations.index'));
        }

        MessageBag::flashMessage('error', 'Ошибка создания приглашения.');
        return $this->redirect(route('invitations.index'));
    }

    /**
     * Отзыв приглашения (POST /invitations/revoke/{id}).
     */
    public function revoke(int $id): RedirectResponse
    {
        $this->request->validateCsrf();
        $userContext = $this->getUserContext();

        if ($this->service(Invitation::class)->revokeInvitation($id, $userContext['id'])) {
            MessageBag::flashMessage('success', 'Приглашение отозвано.');
            return $this->redirect(route('invitations.index'));
        }

        MessageBag::flashMessage('error', 'Не удалось отозвать приглашение.');
        return $this->redirect(route('invitations.index'));
    }

    // =========================================================================
    // РЕГИСТРАЦИЯ ПО ПРИГЛАШЕНИЮ (публичные маршруты)
    // =========================================================================

    /**
     * Страница регистрации по приглашению (GET /register/invite/{code}).
     */
    public function showInviteRegistration(string $code): Response
    {
        if (!$this->isInvitationsEnabled()) {
            MessageBag::flashMessage('error', 'Система приглашений отключена.');
            return $this->redirect('/');
        }

        $invitationModel = $this->service(Invitation::class);
        $invitation = $invitationModel->findByCode($code);

        if (!$invitation || !$invitationModel->isValid($invitation)) {
            MessageBag::flashMessage('error', 'Приглашение недействительно или истек срок действия.');
            return $this->redirect('/');
        }

        return $this->render('register_invite', [
            'title' => 'Регистрация по приглашению',
            'code' => $code,
            'invitation' => $invitation,
            'request' => $this->request
        ]);
    }

    /**
     * Обработка регистрации по приглашению (POST /register/invite/{code}).
     */
    public function registerWithInvite(string $code): RedirectResponse
    {
        if (!$this->isInvitationsEnabled()) {
            MessageBag::flashMessage('error', 'Система приглашений отключена.');
            return $this->redirect('/');
        }

        $this->request->validateCsrf();

        $invitationModel = $this->service(Invitation::class);
        $invitation = $invitationModel->findByCode($code);

        if (!$invitation || !$invitationModel->isValid($invitation)) {
            MessageBag::flashMessage('error', 'Приглашение недействительно или истек срок действия.');
            return $this->redirect('/');
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
            MessageBag::flashMessage('error', $this->formatValidationErrors());
            return $this->redirect('/register/invite/' . $code);
        }

        $userModel = $this->service(User::class);
        $username = (string) $this->request->getParams('username');
        $email = (string) $this->request->getParams('email');

        if ($userModel->findBy('username', $username)) {
            MessageBag::flashMessage('error', 'Имя пользователя уже занято.');
            return $this->redirect('/register/invite/' . $code);
        }

        if ($userModel->findBy('email', $email)) {
            MessageBag::flashMessage('error', 'Email уже зарегистрирован.');
            return $this->redirect('/register/invite/' . $code);
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
            MessageBag::flashMessage('success', 'Регистрация успешна! Добро пожаловать!');
            return $this->redirect(route('auth.login'));
        }

        MessageBag::flashMessage('error', 'Ошибка регистрации.');
        return $this->redirect('/register/invite/' . $code);
    }

    // =========================================================================
    // ЗАПРОС ПРИГЛАШЕНИЯ (публичные маршруты)
    // =========================================================================

    /**
     * Форма запроса приглашения (GET /invite/request).
     */
    public function showRequestForm(): Response
    {
        if (!$this->isInvitationsEnabled()) {
            MessageBag::flashMessage('error', 'Система приглашений отключена.');
            return $this->redirect('/');
        }

        return $this->render('request', [
            'title' => 'Запрос приглашения',
            'request' => $this->request
        ]);
    }

    /**
     * Обработка запроса приглашения (POST /invite/request).
     */
    public function submitRequest(): RedirectResponse
    {
        if (!$this->isInvitationsEnabled()) {
            MessageBag::flashMessage('error', 'Система приглашений отключена.');
            return $this->redirect('/');
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
            MessageBag::flashMessage('error', $this->formatValidationErrors());
            return $this->redirect('/invite/request');
        }

        $requestModel = $this->service(InvitationRequest::class);

        if ($requestModel->hasPendingRequest($email)) {
            MessageBag::flashMessage('error', 'Вы уже отправили запрос. Ожидайте рассмотрения.');
            return $this->redirect('/invite/request');
        }

        $userModel = $this->service(User::class);
        if ($userModel->findBy('email', $email)) {
            MessageBag::flashMessage('error', 'Этот email уже зарегистрирован.');
            return $this->redirect('/invite/request');
        }

        $requestModel->createRequest($email, $reason, $this->request->getIp());

        MessageBag::flashMessage('success', 'Ваш запрос отправлен! Мы рассмотрим его в ближайшее время.');
        return $this->redirect('/');
    }

    // =========================================================================
    // ОТПРАВКА EMAIL-УВЕДОМЛЕНИЙ И УТИЛИТЫ
    // =========================================================================

    private function sendInvitationEmail(string $email, array $invitation): void
    {
        $siteName = app_name();
        $inviteUrl = route('home') . 'register/invite/' . $invitation['code'];
        $expiresAt = dt($invitation['expires_at']);

        $subject = Lang::format('email_invitation_subject', [e($siteName)]);
        $htmlBody = Lang::format('email_invitation_body', [e($siteName), e($inviteUrl), e($expiresAt)]);

        $this->mailer()->send($email, $subject, $htmlBody);
    }

    private function sendApprovedEmail(string $email, string $inviteCode, string $expiresAt): void
    {
        $siteName = app_name();
        $inviteUrl = route('home') . 'register/invite/' . $inviteCode;

        $subject = Lang::format('email_invitation_request_approved_subject', [e($siteName)]);
        $htmlBody = Lang::format('email_invitation_request_approved_body', [e($siteName), e($inviteUrl), e($expiresAt)]);

        $this->mailer()->send($email, $subject, $htmlBody);
    }

    private function sendRejectedEmail(string $email): void
    {
        $siteName = app_name();
        $subject = Lang::format('email_invitation_request_rejected_subject', [e($siteName)]);
        $htmlBody = Lang::format('email_invitation_request_rejected_body', [e($siteName)]);

        $this->mailer()->send($email, $subject, $htmlBody);
    }

    /**
     * Форматировать ошибки валидации в одну строку.
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