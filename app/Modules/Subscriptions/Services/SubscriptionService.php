<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Services;

use App\Modules\Subscriptions\Models\FollowedUser;
use App\Modules\Subscriptions\Models\FollowedTag;
use App\Modules\Subscriptions\Exceptions\SubscriptionValidationException;

class SubscriptionService
{
    private FollowedUser $followedUser;
    private FollowedTag $followedTag;

    public function __construct(FollowedUser $followedUser, FollowedTag $followedTag)
    {
        $this->followedUser = $followedUser;
        $this->followedTag = $followedTag;
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
}