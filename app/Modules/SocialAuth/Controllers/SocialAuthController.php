<?php

declare(strict_types=1);

namespace App\Modules\SocialAuth\Controllers;

use App\BaseController;
use W3a\Core\Http\Response;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Support\MessageBag;

use App\Modules\SocialAuth\Services\SocialAuthService;
use App\Modules\SocialAuth\Services\YandexOAuthService;
use App\Modules\SocialAuth\Services\VKOAuthService;
use App\Modules\Users\Models\User;

class SocialAuthController extends BaseController
{
    // =========================================================================
    // ЯНДЕКС
    // =========================================================================

    public function yandexRedirect(): RedirectResponse
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        $service = $this->service(YandexOAuthService::class);
        return $this->redirect($service->getAuthUrl($state));
    }

    public function yandexCallback(): Response
    {
        if (!$this->validateState()) {
            return $this->redirectWithOauthError();
        }

        if (isset($_GET['error'])) {
            MessageBag::flashMessage('error', 'Авторизация через Яндекс отклонена.');
            return $this->redirect('/login');
        }

        $code = (string)($_GET['code'] ?? '');
        $yandexService = $this->service(YandexOAuthService::class);

        $tokenData = $yandexService->exchangeCode($code);
        if (!$tokenData || empty($tokenData['access_token'])) {
            MessageBag::flashMessage('error', 'Не удалось получить токен Яндекса.');
            return $this->redirect('/login');
        }

        $userInfo = $yandexService->getUserInfo($tokenData['access_token']);
        if (!$userInfo) {
            MessageBag::flashMessage('error', 'Не удалось получить профиль из Яндекса.');
            return $this->redirect('/login');
        }

        $socialAuth = $this->service(SocialAuthService::class);
        $result = $socialAuth->authenticate('yandex', [
            'id' => $userInfo['id'] ?? '',
            'email' => $userInfo['default_email'] ?? $userInfo['emails'][0] ?? null,
            'name' => $userInfo['real_name'] ?? $userInfo['display_name'] ?? '',
            'username' => $userInfo['login'] ?? null,
        ]);

        return $this->loginUser($result['user_id'], $result['is_new']);
    }

    // =========================================================================
    // ВКОНТАКТЕ
    // =========================================================================

    public function vkRedirect(): RedirectResponse
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        $service = $this->service(VKOAuthService::class);
        return $this->redirect($service->getAuthUrl($state));
    }

    public function vkCallback(): Response
    {
        if (!$this->validateState()) {
            return $this->redirectWithOauthError();
        }

        if (isset($_GET['error'])) {
            MessageBag::flashMessage('error', 'Авторизация через VK отклонена.');
            return $this->redirect('/login');
        }

        $code = (string)($_GET['code'] ?? '');
        $vkService = $this->service(VKOAuthService::class);

        $tokenData = $vkService->exchangeCode($code);
        if (!$tokenData || empty($tokenData['access_token'])) {
            MessageBag::flashMessage('error', 'Не удалось получить токен VK.');
            return $this->redirect('/login');
        }

        $userInfo = $vkService->getUserInfo($tokenData['access_token'], (int)$tokenData['user_id']);
        if (!$userInfo) {
            MessageBag::flashMessage('error', 'Не удалось получить профиль из VK.');
            return $this->redirect('/login');
        }

        $socialAuth = $this->service(SocialAuthService::class);
        $result = $socialAuth->authenticate('vk', [
            'id' => (string)($userInfo['id'] ?? $tokenData['user_id']),
            'email' => $tokenData['email'] ?? null,  // ← берём из токена, НЕ из user info!
            'name' => trim(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')),
            'username' => $userInfo['screen_name'] ?? null,
        ]);

        return $this->loginUser($result['user_id'], $result['is_new']);
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    private function validateState(): bool
    {
        $sessionState = $_SESSION['oauth_state'] ?? '';
        $getState = $_GET['state'] ?? '';
        
        unset($_SESSION['oauth_state']);
        
        return !empty($sessionState) && $sessionState === $getState;
    }

    private function redirectWithOauthError(): RedirectResponse
    {
        MessageBag::flashMessage('error', 'Ошибка безопасности OAuth. Попробуйте ещё раз.');
        return $this->redirect('/login');
    }

    private function loginUser(int $userId, bool $isNew): RedirectResponse
    {
        $userModel = $this->container->get(User::class);

        // Проверка бана
        if ($userModel->isBanned($userId)) {
            MessageBag::flashMessage('error', 'Ваш аккаунт заблокирован.');
            return $this->redirect('/login');
        }

        // Проверка активности
        $user = $userModel->getUser($userId);
        if ($user && empty($user['is_active'])) {
            MessageBag::flashMessage('error', 'Ваш аккаунт деактивирован.');
            return $this->redirect('/login');
        }

        // Устанавливаем сессию (используем вашу логику из Auth модуля)
        $_SESSION['user_id'] = $userId;
        $_SESSION['logged_in'] = true;

        if ($isNew) {
            MessageBag::flashMessage('success', 'Добро пожаловать! Аккаунт успешно создан.');
        } else {
            MessageBag::flashMessage('success', 'С возвращением!');
        }

        // Редирект на страницу, с которой пришёл пользователь, или на главную
        $redirect = $_SESSION['oauth_redirect_after'] ?? '/';
        unset($_SESSION['oauth_redirect_after']);

        return $this->redirect($redirect);
    }
}