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
}