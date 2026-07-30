<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\BaseController;
use W3a\Core\Http\Session;
use W3a\Core\Exceptions\NotFoundException;
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

    public function index(): void
    {
        $this->render('index', [
            'title' => 'Участники',
        ]);
    }

    public function profile(string $username): void
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
        }

        $this->render('profile', [
            'title' => 'Профиль пользователя ' . e($user['username']),
            'profileUser' => $user,
            'storiesCount' => $stats['stories_count'] ?? 0,
            'commentsCount' => $stats['comments_count'] ?? 0,
            'userKarma' => $userKarma ?? 0,
            'isMuted' => $isMuted,
        ]);
    }

    public function settings(): void
    {
        $userContext = $this->getUserContext();
        $user = $this->getUserWithProfileOrRedirect($userContext['id']);

        if ($user === null) {
            return;
        }

        $settings = $this->getUserService()->getUserSettings($userContext['id']);

        $this->render('settings', [
            'title' => 'Настройки профиля',
            'user' => $user,
            'settings' => $settings,
            'request' => $this->request
        ]);
    }

    /**
     * Обработка обновления настроек профиля.
     */
    public function updateSettings(): void
    {
        $userContext = $this->getUserContext();
        $user = $this->getUserWithProfileOrRedirect($userContext['id']);

        if ($user === null) {
            return;
        }

        $email = trim($this->request->getParams('email', ''));
        $bio = trim($this->request->getParams('bio', ''));
        $oldAvatarFilename = $user['avatar'] ?? '';
        $newAvatarFilename = $oldAvatarFilename;

        try {
            // 1. Обновление email
            if ($email !== $user['email']) {
                $this->getUserService()->updateEmail($userContext['id'], $email);
            }

            // 2. Обработка аватара
            $avatarFile = $this->request->file('avatar_file');
            if ($avatarFile && $avatarFile['error'] === UPLOAD_ERR_OK) {
                $newAvatarFilename = $this->getAvatarService()->handleUpload($avatarFile, $oldAvatarFilename);
            }

            // 3. Обновление профиля и настроек
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

            // 4. Обновление сессии (это ответственность контроллера)
            $this->container->get(Session::class)->set('user_avatar', $newAvatarFilename);

            $this->redirectWithMessage(route('account.settings'), 'Настройки успешно сохранены.', 'success');

        } catch (UserValidationException | AvatarUploadException $e) {
            $this->redirectWithMessage(route('account.settings'), $e->getMessage(), 'error');
            
        } catch (\Throwable $e) {
            $this->logError($e, 'Users.updateSettings');
            $this->redirectWithMessage(route('account.settings'), 'Произошла непредвиденная ошибка при сохранении.', 'error');
        }
    }

    /**
     * Обработка смены пароля.
     */
    public function updatePassword(): void
    {
        $userContext = $this->getUserContext();
        $currentPassword = $this->request->getParams('current_password', '');
        $newPassword = $this->request->getParams('new_password', '');

        if (strlen($newPassword) < 6) {
            $this->redirectWithMessage(route('account.settings'), 'Пароль должен быть не менее 6 символов.', 'error');
            return;
        }

        try {
            $this->getUserService()->changePassword($userContext['id'], $currentPassword, $newPassword);
            $this->redirectWithMessage(route('account.settings'), 'Пароль успешно изменён.', 'success');
            
        } catch (UserValidationException | UserNotFoundException $e) {
            $this->redirectWithMessage(route('account.settings'), $e->getMessage(), 'error');
            
        } catch (\Throwable $e) {
            $this->logError($e, 'Users.updatePassword');
            $this->redirectWithMessage(route('account.settings'), 'Произошла непредвиденная ошибка.', 'error');
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

    private function getUserWithProfileOrRedirect(int $userId): ?array
    {
        $user = $this->getUserService()->getUserWithProfile($userId);

        if (!$user) {
            $this->redirect('/');
            return null;
        }

        return $user;
    }
}