<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Models;

use W3a\Core\Contracts\DatabaseInterface;

class FollowedUser
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function follow(int $userId, int $targetUserId): bool
    {
        return $this->db->execute(
            "INSERT INTO `followed_users` (`user_id`, `followed_user_id`) VALUES (:user_id, :followed_user_id)",
            ['user_id' => $userId, 'followed_user_id' => $targetUserId]
        ) > 0;
    }

    public function unfollow(int $userId, int $targetUserId): bool
    {
        return $this->db->execute(
            "DELETE FROM `followed_users` WHERE `user_id` = :user_id AND `followed_user_id` = :followed_user_id",
            ['user_id' => $userId, 'followed_user_id' => $targetUserId]
        ) > 0;
    }

    public function isFollowing(int $userId, int $targetUserId): bool
    {
        return (bool)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `followed_users` WHERE `user_id` = :user_id AND `followed_user_id` = :followed_user_id",
            ['user_id' => $userId, 'followed_user_id' => $targetUserId]
        );
    }

    public function getFollowedUserIds(int $userId): array
    {
        $result = $this->db->fetchAll(
            "SELECT `followed_user_id` FROM `followed_users` WHERE `user_id` = :user_id",
            ['user_id' => $userId]
        );
        return array_column($result, 'followed_user_id');
    }

    /**
     * Количество подписчиков пользователя.
     */
    public function getFollowersCount(int $userId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `followed_users` WHERE `followed_user_id` = :user_id",
            ['user_id' => $userId]
        );
    }
}