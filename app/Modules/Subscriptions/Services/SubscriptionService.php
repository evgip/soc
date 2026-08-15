<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Services;

use App\Modules\Subscriptions\Models\FollowedUser;
use App\Modules\Subscriptions\Models\FollowedTag;
use App\Modules\Subscriptions\Models\FollowedCollection;
use App\Modules\Subscriptions\Exceptions\SubscriptionValidationException;

class SubscriptionService
{
    private FollowedUser $followedUser;
    private FollowedTag $followedTag;
	private FollowedCollection $followedCollection;

	public function __construct(
		FollowedUser $followedUser, 
		FollowedTag $followedTag,
		FollowedCollection $followedCollection
	) {
		$this->followedUser = $followedUser;
		$this->followedTag = $followedTag;
		$this->followedCollection = $followedCollection;
	}

    public function toggleUser(int $userId, int $targetUserId): bool
    {
        if ($userId === $targetUserId) {
            throw new SubscriptionValidationException('Нельзя подписаться на самого себя');
        }

        if ($this->followedUser->isFollowing($userId, $targetUserId)) {
            $this->followedUser->unfollow($userId, $targetUserId);
            return false; // Отписались
        }

        $this->followedUser->follow($userId, $targetUserId);
        return true; // Подписались
    }

    public function toggleTag(int $userId, int $tagId): bool
    {
        if ($this->followedTag->isFollowing($userId, $tagId)) {
            $this->followedTag->unfollow($userId, $tagId);
            return false;
        }
        $this->followedTag->follow($userId, $tagId);
        return true;
    }

    public function getFollowedUserIds(int $userId): array {
        return $this->followedUser->getFollowedUserIds($userId);
    }

    public function getFollowedTagIds(int $userId): array {
        return $this->followedTag->getFollowedTagIds($userId);
    }

	public function isFollowingUser(int $userId, int $targetUserId): bool {
		return $this->followedUser->isFollowing($userId, $targetUserId);
	}
	
	/**
	 * Переключить подписку на коллекцию (toggle).
	 * 
	 * @return bool true если подписались, false если отписались
	 */
	public function toggleCollection(int $userId, int $collectionId): bool
	{
		if ($this->followedCollection->isFollowing($userId, $collectionId)) {
			$this->followedCollection->unfollow($userId, $collectionId);
			return false;
		}
		$this->followedCollection->follow($userId, $collectionId);
		return true;
	}

	/**
	 * Проверить, подписан ли пользователь на коллекцию.
	 */
	public function isFollowingCollection(int $userId, int $collectionId): bool
	{
		return $this->followedCollection->isFollowing($userId, $collectionId);
	}

	/**
	 * Получить ID всех подписчиков коллекции (для уведомлений).
	 */
	public function getCollectionFollowerIds(int $collectionId): array
	{
		return $this->followedCollection->getFollowerIds($collectionId);
	}

	/**
	 * Получить количество подписчиков коллекции.
	 */
	public function getCollectionFollowersCount(int $collectionId): int
	{
		return $this->followedCollection->getFollowersCount($collectionId);
	}

	/**
	 * Получить ID коллекций, на которые подписан пользователь.
	 */
	public function getFollowedCollectionIds(int $userId): array
	{
		return $this->followedCollection->getFollowedCollectionIds($userId);
	}
}