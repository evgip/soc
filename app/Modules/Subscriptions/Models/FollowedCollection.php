<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Models;

use W3a\Core\Contracts\DatabaseInterface;

/**
 * Модель подписок пользователей на коллекции (серии статей).
 * 
 * При добавлении новой статьи в коллекцию, все подписчики получают
 * уведомление типа 'collection_new_part'.
 */
class FollowedCollection
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Подписаться на коллекцию.
     */
    public function follow(int $userId, int $collectionId): bool
    {
        return $this->db->execute(
            "INSERT INTO `followed_collections` (`user_id`, `collection_id`) 
             VALUES (:user_id, :collection_id)",
            ['user_id' => $userId, 'collection_id' => $collectionId]
        ) > 0;
    }

    /**
     * Отписаться от коллекции.
     */
    public function unfollow(int $userId, int $collectionId): bool
    {
        return $this->db->execute(
            "DELETE FROM `followed_collections` 
             WHERE `user_id` = :user_id AND `collection_id` = :collection_id",
            ['user_id' => $userId, 'collection_id' => $collectionId]
        ) > 0;
    }

    /**
     * Проверить, подписан ли пользователь на коллекцию.
     */
    public function isFollowing(int $userId, int $collectionId): bool
    {
        return (bool) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `followed_collections` 
             WHERE `user_id` = :user_id AND `collection_id` = :collection_id",
            ['user_id' => $userId, 'collection_id' => $collectionId]
        );
    }

    /**
     * Получить ID коллекций, на которые подписан пользователь.
     */
    public function getFollowedCollectionIds(int $userId): array
    {
        $result = $this->db->fetchAll(
            "SELECT `collection_id` FROM `followed_collections` 
             WHERE `user_id` = :user_id",
            ['user_id' => $userId]
        );
        return array_column($result, 'collection_id');
    }

    /**
     * Получить ID всех подписчиков конкретной коллекции.
     * Используется для отправки уведомлений при добавлении новой части.
     */
    public function getFollowerIds(int $collectionId): array
    {
        $result = $this->db->fetchAll(
            "SELECT `user_id` FROM `followed_collections` 
             WHERE `collection_id` = :collection_id",
            ['collection_id' => $collectionId]
        );
        return array_column($result, 'user_id');
    }

    /**
     * Получить количество подписчиков коллекции.
     */
    public function getFollowersCount(int $collectionId): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `followed_collections` 
             WHERE `collection_id` = :collection_id",
            ['collection_id' => $collectionId]
        );
    }
}