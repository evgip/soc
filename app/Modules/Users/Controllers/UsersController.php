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

use App\Modules\Users\Requests\UpdateSettingsRequest;
use App\Modules\Users\Requests\ChangePasswordRequest;

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
		
		$stats = $userModel->getProfileStats((int)$user['id']);
		$userKarma = $userModel->getUserKarma((int)$user['id']);

		$userContext = $this->getUserContext();

		$isMuted = false;
		$isFollowing = false;
		if ($userContext['isLoggedIn'] && (int)$user['id'] !== $userContext['id']) {
			$muteService = $this->service(\App\Modules\Muted\Services\MuteService::class);
			$isMuted = $muteService->isMuted($userContext['id'], (int)$user['id']);

			$subscriptionService = $this->service(\App\Modules\Subscriptions\Services\SubscriptionService::class);
			$isFollowing = $subscriptionService->isFollowingUser($userContext['id'], (int)$user['id']);
		}

		$feed = $this->service(\App\Modules\Stories\Services\StoryFeedBuilder::class)->build(
			tagslug: '',
			author: $username,
			userContext: $userContext,
			canUserDownvote: $this->canUserDownvote($userContext['id'] ?? 0),
			pageData: ['title' => 'Публикации ' . e($username)]
		);

		// Загружаем коллекции пользователя
		$collectionModel = $this->container->get(\App\Modules\Collections\Models\Collection::class);
		$allCollections = $collectionModel->getByAuthor((int)$user['id']);
		
		// Фильтруем приватные коллекции для не-владельцев
		$isOwner = $userContext['isLoggedIn'] && $userContext['id'] === (int)$user['id'];
		if (!$isOwner) {
			$allCollections = array_filter($allCollections, fn($c) => !empty($c['is_public']));
			$allCollections = array_values($allCollections);
		}
		
		$collectionsCount = count($allCollections);
		
		// Берём только первые 3 коллекции для превью
		$collections = array_slice($allCollections, 0, 3);
		
		// Формируем полные URL обложек
		$coverService = $this->service(\App\Modules\Collections\Services\CollectionCoverService::class);
		foreach ($collections as &$collection) {
			$collection['cover_url'] = !empty($collection['cover_image'])
				? $coverService->getCoverUrl($collection['cover_image'])
				: null;
		}
		unset($collection);

		return $this->render('profile', [
			'title' => 'Профиль пользователя ' . e($user['username']),
			'profileUser' => $user,
			'userKarma' => $userKarma ?? 0,
			'isMuted' => $isMuted,
			'isFollowing' => $isFollowing,
			'storiesCount' => $stats['stories_count'] ?? count($feed->stories),
			'commentsCount' => $stats['comments_count'] ?? 0,
			'collectionsCount' => $collectionsCount,  
			'collections' => $collections,            
			'stories' => $feed->stories,
			'currentPage' => $feed->currentPage,
			'totalPages' => $feed->totalPages,
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

    /**
     * Обновление настроек профиля пользователя.
     */
	public function updateSettings(): RedirectResponse
	{
		$userContext = $this->getUserContext();
		$userOrRedirect = $this->getUserWithProfileOrRedirect($userContext['id']);

		if ($userOrRedirect instanceof RedirectResponse) {
			return $userOrRedirect;
		}
		
		$user = $userOrRedirect;
		$targetUrl = route('account.settings');

		$validated = $this->validateForm(
			new UpdateSettingsRequest($this->request, $this->container)
		);
		
		if ($validated instanceof Response) {
			return $this->redirect($targetUrl);
		}

		try {
			$email = trim($validated['email'] ?? '');
			if ($email !== $user['email']) {
				$this->getUserService()->updateEmail($userContext['id'], $email);
			}

			$oldAvatarFilename = $user['avatar'] ?? '';
			$newAvatarFilename = $oldAvatarFilename;

			$avatarFile = $this->request->file('avatar_file');
			if ($avatarFile && $avatarFile['error'] === UPLOAD_ERR_OK) {
				$newAvatarFilename = $this->getAvatarService()->handleUpload($avatarFile, $oldAvatarFilename);
			}

			$bio = trim($validated['bio'] ?? '');
			$this->getUserService()->updateProfile($userContext['id'], [
				'bio'    => $bio,
				'avatar' => $newAvatarFilename
			]);

			$this->getUserService()->updateSettings($userContext['id'], [
				'notify_on_reply'               => (int)($validated['notify_on_reply'] ?? 0),
				'notify_on_story_comment'       => (int)($validated['notify_on_story_comment'] ?? 0),
				'notify_on_mention'             => (int)($validated['notify_on_mention'] ?? 0),
				'notify_on_message'             => (int)($validated['notify_on_message'] ?? 0),
				'notify_on_collection_update'   => (int)($validated['notify_on_collection_update'] ?? 0),
				'email_notifications'           => (int)($validated['email_notifications'] ?? 0),
			]);

			$this->container->get(Session::class)->set('user_avatar', $newAvatarFilename);

			MessageBag::flashMessage('success', 'Настройки успешно сохранены.');
			return $this->redirect($targetUrl);

		} catch (UserValidationException | AvatarUploadException $e) {
			MessageBag::flashMessage('error', $e->getMessage());
			return $this->redirect($targetUrl);

		} catch (\Throwable $e) {
			$this->logError($e, 'Users.updateSettings');
			MessageBag::flashMessage('error', 'Произошла непредвиденная ошибка при сохранении.');
			return $this->redirect($targetUrl);
		}
	}

    /**
     * Изменение пароля пользователя.
     */
	public function updatePassword(): RedirectResponse
	{
		$userContext = $this->getUserContext();
		$targetUrl = route('account.settings');

		$validated = $this->validateForm(
			new ChangePasswordRequest($this->request, $this->container)
		);
		
		if ($validated instanceof Response) {
			return $this->redirect($targetUrl);
		}

		try {
			$this->getUserService()->changePassword(
				$userContext['id'],
				$validated['current_password'],
				$validated['new_password']
			);
			
			MessageBag::flashMessage('success', 'Пароль успешно изменён.');
			return $this->redirect($targetUrl);
			
		} catch (UserValidationException | UserNotFoundException $e) {
			MessageBag::flashMessage('error', $e->getMessage());
			return $this->redirect($targetUrl);

		} catch (\Throwable $e) {
			$this->logError($e, 'Users.updatePassword');
			MessageBag::flashMessage('error', 'Произошла непредвиденная ошибка.');
			return $this->redirect($targetUrl);
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