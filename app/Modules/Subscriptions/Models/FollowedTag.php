<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Models;

use W3a\Core\Contracts\DatabaseInterface;

class FollowedTag
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function follow(int $userId, int $tagId): bool
    {
        return $this->db->execute(
            "INSERT INTO `followed_tags` (`user_id`, `tag_id`) VALUES (:user_id, :tag_id)",
            ['user_id' => $userId, 'tag_id' => $tagId]
        ) > 0;
    }

    public function unfollow(int $userId, int $tagId): bool
    {
        return $this->db->execute(
            "DELETE FROM `followed_tags` WHERE `user_id` = :user_id AND `tag_id` = :tag_id",
            ['user_id' => $userId, 'tag_id' => $tagId]
        ) > 0;
    }

    public function isFollowing(int $userId, int $tagId): bool
    {
        return (bool)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `followed_tags` WHERE `user_id` = :user_id AND `tag_id` = :tag_id",
            ['user_id' => $userId, 'tag_id' => $tagId]
        );
    }

    public function getFollowedTagIds(int $userId): array
    {
        $result = $this->db->fetchAll(
            "SELECT `tag_id` FROM `followed_tags` WHERE `user_id` = :user_id",
            ['user_id' => $userId]
        );
        return array_column($result, 'tag_id');
    }
}