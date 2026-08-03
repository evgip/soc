<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\BaseController;
use W3a\Core\Http\Session;
use W3a\Core\Http\Response;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Exceptions\NotFoundException;
use W3a\Core\Support\MessageBag;

use App\Modules\Users\Services\UserService;
use App\Modules\Users\Services\AvatarService;
use App\Modules\Users\Models\User;
use App\Modules\Users\Exceptions\UserValidationException;
use App\Modules\Users\Exceptions\UserNotFoundException;
use App\Modules\Users\Exceptions\AvatarUploadException;

/**
 * Контроллер для управления профилями пользователей и настройками аккаунта.
 */
class UsersController extends BaseController
{
    private function getUserService(): UserService
    {
        return $this->service(UserService::class);
    }

    private function getAvatarService(): AvatarService
    {
        return $this->service(AvatarService::class);
    }

    // =========================================================================
    // ОСНОВНЫЕ ДЕЙСТВИЯ
    // =========================================================================

    public function index(): ViewResponse
    {
        return $this->render('index', [
            'title' => 'Участники',
        ]);
    }

    public function profile(string $username): ViewResponse
    {
        $user = $this->getUserByUsername(trim($username));

        $profile = $user['profile'] ?? [];
        $user['bio'] = $profile['bio'] ?? null;
        $user['avatar'] = $profile['avatar'] ?? null;

        $userModel = $this->container->get(User::class);
        $banInfo = $userModel->getBanInfo((int)$user['id']);
        $user['is_banned'] = $banInfo !== null;
        $user['ban_reason'] = $banInfo['reason'] ?? null;
        $user['banned_at'] = $banInfo['created_at'] ?? null;
        $user['expires_at'] = $banInfo['expires_at'] ?? null;

        $stats = $userModel->getProfileStats((int)$user['id']);
        $userKarma = $userModel->getUserKarma((int)$user['id']);

        $userContext = $this->getUserContext();

        $isMuted = false;
        if ($userContext['isLoggedIn'] && (int)$user['id'] !== $userContext['id']) {
            $muteService = $this->service(\App\Modules\Muted\Services\MuteService::class);
            $isMuted = $muteService->isMuted($userContext['id'], (int)$user['id']);

            $subscriptionService = $this->service(\App\Modules\Subscriptions\Services\SubscriptionService::class);
            $isFollowing = $subscriptionService->isFollowingUser($userContext['id'], (int)$user['id']);
        }

        return $this->render('profile', [
            'title' => 'Профиль пользователя ' . e($user['username']),
            'profileUser' => $user,
            'storiesCount' => $stats['stories_count'] ?? 0,
            'commentsCount' => $stats['comments_count'] ?? 0,
            'userKarma' => $userKarma ?? 0,
            'isMuted' => $isMuted,
			'isFollowing' => $isFollowing,
        ]);
    }

    /**
     * Используем общий Response, так как метод может вернуть 
     * либо ViewResponse (успех), либо RedirectResponse (если пользователь не найден).
     */
    public function settings(): Response
    {
        $userContext = $this->getUserContext();
        
        // Вспомогательный метод теперь сам вернет RedirectResponse, если пользователя нет
        $userOrRedirect = $this->getUserWithProfileOrRedirect($userContext['id']);

        if ($userOrRedirect instanceof RedirectResponse) {
            return $userOrRedirect;
        }
        
        $user = $userOrRedirect;
        $settings = $this->getUserService()->getUserSettings($userContext['id']);

        return $this->render('settings', [
            'title' => 'Настройки профиля',
            'user' => $user,
            'settings' => $settings,
            'request' => $this->request
        ]);
    }

    // =========================================================================
    // ОБРАБОТКА ФОРМ (POST)
    // =========================================================================

    public function updateSettings(): RedirectResponse
    {
        $userContext = $this->getUserContext();
        $userOrRedirect = $this->getUserWithProfileOrRedirect($userContext['id']);

        if ($userOrRedirect instanceof RedirectResponse) {
            return $userOrRedirect;
        }
        
        $user = $userOrRedirect;

        $email = trim($this->request->getParams('email', ''));
        $bio = trim($this->request->getParams('bio', ''));
        $oldAvatarFilename = $user['avatar'] ?? '';
        $newAvatarFilename = $oldAvatarFilename;

        $errorMessage = null;

        try {
            if ($email !== $user['email']) {
                $this->getUserService()->updateEmail($userContext['id'], $email);
            }

            $avatarFile = $this->request->file('avatar_file');
            if ($avatarFile && $avatarFile['error'] === UPLOAD_ERR_OK) {
                $newAvatarFilename = $this->getAvatarService()->handleUpload($avatarFile, $oldAvatarFilename);
            }

            $this->getUserService()->updateProfile($userContext['id'], [
                'bio' => $bio,
                'avatar' => $newAvatarFilename
            ]);

            $this->getUserService()->updateSettings($userContext['id'], [
                'notify_on_reply' => $this->request->getParams('notify_on_reply') ? 1 : 0,
                'notify_on_story_comment' => $this->request->getParams('notify_on_story_comment') ? 1 : 0,
                'notify_on_mention' => $this->request->getParams('notify_on_mention') ? 1 : 0,
                'notify_on_message' => $this->request->getParams('notify_on_message') ? 1 : 0,
                'email_notifications' => $this->request->getParams('email_notifications') ? 1 : 0,
            ]);

            $this->container->get(Session::class)->set('user_avatar', $newAvatarFilename);

        } catch (UserValidationException | AvatarUploadException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            $this->logError($e, 'Users.updateSettings');
            $errorMessage = 'Произошла непредвиденная ошибка при сохранении.';
        }

        $targetUrl = route('account.settings');
        
        if ($errorMessage !== null) {
            MessageBag::flashMessage('error', $errorMessage);
            return $this->redirect($targetUrl);
        }
        
        MessageBag::flashMessage('success', 'Настройки успешно сохранены.');
        return $this->redirect($targetUrl);
    }

    public function updatePassword(): RedirectResponse
    {
        $userContext = $this->getUserContext();
        $currentPassword = $this->request->getParams('current_password', '');
        $newPassword = $this->request->getParams('new_password', '');

        if (strlen($newPassword) < 6) {
            MessageBag::flashMessage('error', 'Пароль должен быть не менее 6 символов.');
            return $this->redirect(route('account.settings'));
        }

        try {
            $this->getUserService()->changePassword($userContext['id'], $currentPassword, $newPassword);
            MessageBag::flashMessage('success', 'Пароль успешно изменён.');
            return $this->redirect(route('account.settings'));
            
        } catch (UserValidationException | UserNotFoundException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirect(route('account.settings'));
        } catch (\Throwable $e) {
            $this->logError($e, 'Users.updatePassword');
            MessageBag::flashMessage('error', 'Произошла непредвиденная ошибка.');
            return $this->redirect(route('account.settings'));
        }
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    private function getUserByUsername(string $username): array
    {
        $userModel = $this->container->get(User::class);
        $user = $userModel->findBy('username', $username);

        if (!$user) {
            throw new NotFoundException("Пользователь <i>{$username}</i> не найден.");
        }

        $user['profile'] = $userModel->getProfile((int)$user['id']);
        return $user;
    }

    /**
     * Возвращает массив данных пользователя ИЛИ объект RedirectResponse для перенаправления.
     */
    private function getUserWithProfileOrRedirect(int $userId): array|RedirectResponse
    {
        $user = $this->getUserService()->getUserWithProfile($userId);

        if (!$user) {
            return $this->redirect('/');
        }

        return $user;
    }
}