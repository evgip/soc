<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Controllers;

use App\BaseController;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Support\MessageBag;
use App\Modules\Subscriptions\Services\SubscriptionService;

class SubscriptionController extends BaseController
{
    public function toggleUser(string $id): RedirectResponse
    {
        $userContext = $this->getUserContext();
        $targetUserId = (int)$id;

        if (!$userContext['isLoggedIn']) {
            return $this->redirect('/');
        }

        if ($userContext['id'] === $targetUserId) {
            MessageBag::flashMessage('error', 'Нельзя подписаться на самого себя');
            return $this->redirectBack();
        }

        try {
            $service = $this->service(SubscriptionService::class);
            $isFollowing = $service->toggleUser($userContext['id'], $targetUserId);

            $message = $isFollowing ? 'Вы подписались на пользователя' : 'Вы отписались от пользователя';
            MessageBag::flashMessage('success', $message);
        } catch (\Throwable $e) {
            $this->logError($e, 'Subscription.toggleUser');
            MessageBag::flashMessage('error', 'Произошла ошибка при изменении подписки');
        }

        return $this->redirectBack();
    }

    public function toggleTag(string $id): RedirectResponse
    {
        $userContext = $this->getUserContext();
        $tagId = (int)$id;

        if (!$userContext['isLoggedIn']) {
            return $this->redirect('/');
        }

        try {
            $service = $this->service(SubscriptionService::class);
            $isFollowing = $service->toggleTag($userContext['id'], $tagId);

            $message = $isFollowing ? 'Вы подписались на тег' : 'Вы отписались от тега';
            MessageBag::flashMessage('success', $message);
        } catch (\Throwable $e) {
            $this->logError($e, 'Subscription.toggleTag');
            MessageBag::flashMessage('error', 'Произошла ошибка при изменении подписки');
        }

        return $this->redirectBack();
    }
	
	/**
	 * Переключить подписку на коллекцию (серию).
	 */
	public function toggleCollection(string $id): RedirectResponse
	{
		$userContext = $this->getUserContext();
		$collectionId = (int)$id;

		if (!$userContext['isLoggedIn']) {
			return $this->redirect('/');
		}

		try {
			// Проверяем, не является ли пользователь автором коллекции
			$collectionModel = $this->container->get(\App\Modules\Collections\Models\Collection::class);
			$collection = $collectionModel->find($collectionId);

			if (!$collection || !empty($collection['deleted_at'])) {
				MessageBag::flashMessage('error', 'Коллекция не найдена');
				return $this->redirectBack();
			}

			if ((int)$collection['author_id'] === $userContext['id']) {
				MessageBag::flashMessage('error', 'Нельзя подписаться на свою коллекцию');
				return $this->redirectBack();
			}

			$service = $this->service(SubscriptionService::class);
			$isFollowing = $service->toggleCollection($userContext['id'], $collectionId);

			$message = $isFollowing 
				? 'Вы подписались на серию. Будем уведомлять о новых частях.' 
				: 'Вы отписались от серии';
			MessageBag::flashMessage('success', $message);
		} catch (\Throwable $e) {
			$this->logError($e, 'Subscription.toggleCollection');
			MessageBag::flashMessage('error', 'Произошла ошибка при изменении подписки');
		}

		return $this->redirectBack();
	}
}