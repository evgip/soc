<?php
// app/Modules/Saved/Models/SavedStory.php

declare(strict_types=1);

namespace App\Modules\Saved\Models;

use W3a\Core\Model;
use W3a\Core\Database;
use W3a\Core\Logger;

class SavedStory extends Model
{
    protected string $table = 'saved_stories';

    protected array $fillable = ['user_id', 'story_id'];

    /**
     * Сохранить историю в закладки
     */
    public function save(int $userId, int $storyId): bool
    {
        try {
            $sql = "INSERT INTO `saved_stories` (`user_id`, `story_id`) 
                    VALUES (:user_id, :story_id)
                    ON DUPLICATE KEY UPDATE `created_at` = CURRENT_TIMESTAMP";
            
            return $this->db->execute($sql, [
                'user_id' => $userId,
                'story_id' => $storyId,
            ]) > 0;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("SavedStory::save failed: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Удалить историю из закладок
     */
    public function unsave(int $userId, int $storyId): bool
    {
        return $this->db->execute(
            "DELETE FROM `saved_stories` WHERE `user_id` = ? AND `story_id` = ?",
            [$userId, $storyId]
        ) > 0;
    }

    /**
     * Проверить, сохранена ли история
     */
    public function isSaved(int $userId, int $storyId): bool
    {
        return (bool)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `saved_stories` WHERE `user_id` = ? AND `story_id` = ?",
            [$userId, $storyId]
        );
    }

    /**
     * Получить список сохранённых историй пользователя с пагинацией
     */
    public function getUserSaved(int $userId, int $limit, int $offset): array {
        $repo = new \App\Modules\Stories\Repositories\StoryRepository($this->db);
        
        return $repo->fromSaved($userId)
                    ->withAuthor()
                    ->withAvatar()
                    ->withTags()
                    ->addWhere('s.deleted_at IS NULL')
                    ->setOrderBy('ss.created_at DESC')
                    ->paginate($limit, $offset)
                    ->get();
    }

    /**
     * Получить общее количество сохранённых историй
     */
    public function getUserSavedCount(int $userId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `saved_stories` ss
             JOIN `stories` s ON ss.story_id = s.id
             WHERE ss.user_id = ? AND s.deleted_at IS NULL",
            [$userId]
        );
    }

    /**
     * Получить массив сохранённых story_id для пользователя (для отметки в ленте)
     */
    public function getUserSavedStoryIds(int $userId): array
    {
        $result = $this->db->fetchAll(
            "SELECT `story_id` FROM `saved_stories` WHERE `user_id` = ?",
            [$userId]
        );
        return array_column($result, 'story_id');
    }
}