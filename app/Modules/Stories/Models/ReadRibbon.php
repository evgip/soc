<?php

declare(strict_types=1);

namespace App\Modules\Stories\Models;

use W3a\Core\Model;
use W3a\Core\Database;
use W3a\Core\Logger;

class ReadRibbon extends Model
{
    protected string $table = 'read_ribbons';

    protected array $fillable = [
        'user_id',
        'story_id',
        'last_read_comment_id',
    ];

    /*
        -- Синхронизация всех отметок с реальным состоянием комментариев
        UPDATE read_ribbons rr
        SET rr.last_read_comment_id = (
            SELECT COALESCE(MAX(c.id), 0)
            FROM (SELECT id, story_id FROM comments WHERE deleted_at IS NULL) c
            WHERE c.story_id = rr.story_id
        );
    */

    /**
     * Отметить историю как прочитанную до указанного комментария
     * Использует UPSERT — создаёт запись или обновляет существующую
     *
     * @param int $userId         ID пользователя
     * @param int $storyId        ID истории
     * @param int $lastCommentId  ID последнего комментария на момент просмотра
     */
    public function markAsRead(int $userId, int $storyId, int $lastCommentId): void
    {
        if ($userId <= 0 || $storyId <= 0) {
            return;
        }

        try {
            $sql = "INSERT INTO `read_ribbons` 
                        (`user_id`, `story_id`, `last_read_comment_id`, `updated_at`) 
                    VALUES 
                        (:user_id, :story_id, :last_comment_id, NOW())
                    ON DUPLICATE KEY UPDATE 
                        `last_read_comment_id` = GREATEST(`last_read_comment_id`, VALUES(`last_read_comment_id`)),
                        `updated_at` = NOW()";

            $this->db->query($sql, [
                'user_id'         => $userId,
                'story_id'        => $storyId,
                'last_comment_id' => $lastCommentId,
            ]);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("ReadRibbon::markAsRead failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Получить отметки прочтения для списка историй (одним запросом)
     * Возвращает ассоциативный массив: story_id => last_read_comment_id
     *
     * @param int   $userId   ID пользователя
     * @param array $storyIds Список ID историй
     * @return array<int, int>
     */
    public function getForStories(int $userId, array $storyIds): array
    {
        if ($userId <= 0 || empty($storyIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($storyIds), '?'));

        $sql = "SELECT `story_id`, `last_read_comment_id` 
                FROM `read_ribbons` 
                WHERE `user_id` = ? AND `story_id` IN ($placeholders)";

        $stmt = $this->db->query($sql, array_merge([$userId], $storyIds));

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int)$row['story_id']] = (int)$row['last_read_comment_id'];
        }

        return $result;
    }

    /**
     * Реальный подсчёт новых комментариев (без использования read_ribbons)
     * Для проверки рассинхронизации
     */
    public function countRealNewComments(int $storyId, int $userId): int
    {
        // Получаем last_read_comment_id
        $lastReadId = (int)$this->db->fetchColumn(
            "SELECT `last_read_comment_id` FROM `read_ribbons` 
             WHERE `user_id` = ? AND `story_id` = ?",
            [$userId, $storyId]
        );

        // Считаем реальные новые (неудалённые) комментарии
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `comments` 
             WHERE `story_id` = ? 
               AND `id` > ? 
               AND `deleted_at` IS NULL",
            [$storyId, $lastReadId]
        );
    }

	/**
	 * Пакетный подсчёт количества новых комментариев для списка историй
	 * Один эффективный запрос вместо N+1
	 * @param int   $userId        ID пользователя
	 * @param array $storyIds      Список ID историй
	 * @param array $mutedUserIds  Список ID замьюченных пользователей
	 * @return array<int, int>     story_id => количество новых комментариев
	 */
	public function getNewCommentsCounts(int $userId, array $storyIds, array $mutedUserIds = []): array
	{
		if ($userId <= 0 || empty($storyIds)) {
			return [];
		}

		$storyPlaceholders = implode(',', array_fill(0, count($storyIds), '?'));

		$sql = "SELECT 
					s.id AS story_id,
					COUNT(c.id) AS new_count
				FROM `stories` s
				LEFT JOIN `read_ribbons` rr 
					ON rr.story_id = s.id AND rr.user_id = ?
				LEFT JOIN `comments` c 
					ON c.story_id = s.id 
					AND c.id > COALESCE(rr.last_read_comment_id, 0)
					AND c.deleted_at IS NULL";

		$bindings = [$userId];

		// Исключаем комментарии от замьюченных пользователей
		if (!empty($mutedUserIds)) {
			$mutedPlaceholders = implode(',', array_fill(0, count($mutedUserIds), '?'));
			$sql .= " AND c.user_id NOT IN ({$mutedPlaceholders})";
			$bindings = array_merge($bindings, $mutedUserIds);
		}

		$sql .= " WHERE s.id IN ({$storyPlaceholders})
				GROUP BY s.id";

		$bindings = array_merge($bindings, $storyIds);

		$stmt = $this->db->query($sql, $bindings);

		$result = [];
		foreach ($stmt->fetchAll() as $row) {
			$result[(int)$row['story_id']] = (int)$row['new_count'];
		}

		return $result;
	}

    /**
     * Подсчёт новых комментариев для одной истории
     */
    public function getNewCommentsCount(int $userId, int $storyId): int
    {
        $counts = $this->getNewCommentsCounts($userId, [$storyId]);
        return $counts[$storyId] ?? 0;
    }

    /**
     * Удалить отметки для истории (вызывается при удалении истории)
     * Каскадный FK уже делает это автоматически, но оставим для явности
     */
    public function clearForStory(int $storyId): void
    {
        $this->db->execute("DELETE FROM `read_ribbons` WHERE `story_id` = ?", [$storyId]);
    }

    /**
     * Принудительный сброс отметки для одной истории
     * Устанавливает last_read_comment_id = последний существующий комментарий
     */
    public function syncForUserAndStory(int $userId, int $storyId): int
    {
        if ($userId <= 0 || $storyId <= 0) {
            return 0;
        }

        // Находим ID последнего НЕУДАЛЁННОГО комментария
        $lastCommentId = (int)$this->db->fetchColumn(
            "SELECT COALESCE(MAX(id), 0) AS last_id 
             FROM `comments` 
             WHERE `story_id` = ? AND `deleted_at` IS NULL",
            [$storyId]
        );

        // Обновляем (или создаём) отметку
        $this->markAsRead($userId, $storyId, $lastCommentId);

        return $lastCommentId;
    }

    /**
     * Сброс всех отметок пользователя (на случай массового рассинхрона)
     */
    public function resetAllForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        // Получаем список историй пользователя
        $stmt = $this->db->query(
            "SELECT DISTINCT `story_id` FROM `read_ribbons` WHERE `user_id` = ?",
            [$userId]
        );
        $storyIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $count = 0;
        foreach ($storyIds as $storyId) {
            $this->syncForUserAndStory($userId, (int)$storyId);
            $count++;
        }

        return $count;
    }

    /**
     * Удалить отметку для истории (полный сброс — история будет показана как "новая")
     */
    public function forgetForUserAndStory(int $userId, int $storyId): void
    {
        $this->db->execute(
            "DELETE FROM `read_ribbons` WHERE `user_id` = ? AND `story_id` = ?",
            [$userId, $storyId]
        );
    }
	
	/**
	 * Пакетная синхронизация отметок прочтения для списка историй
	 * Находит последний комментарий для каждой истории и обновляет read_ribbons
	 * 
	 * @param int   $userId   ID пользователя
	 * @param array $storyIds Список ID историй
	 * @return void
	 */
	public function syncForStories(int $userId, array $storyIds): void
	{
		if ($userId <= 0 || empty($storyIds)) {
			return;
		}

		try {
			// 1. Одним запросом находим последний комментарий для каждой истории
			$placeholders = implode(',', array_fill(0, count($storyIds), '?'));
			
			$sql = "SELECT story_id, COALESCE(MAX(id), 0) AS last_comment_id
					FROM `comments`
					WHERE `story_id` IN ({$placeholders}) 
					  AND `deleted_at` IS NULL
					GROUP BY `story_id`";

			$stmt = $this->db->query($sql, $storyIds);
			$lastComments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

			if (empty($lastComments)) {
				return;
			}

			// 2. Формируем batch UPSERT
			$valuesPlaceholders = [];
			$values = [];
			
			foreach ($lastComments as $row) {
				$valuesPlaceholders[] = '(?, ?, ?)';
				$values[] = $userId;
				$values[] = (int)$row['story_id'];
				$values[] = (int)$row['last_comment_id'];
			}

			$sql = "INSERT INTO `read_ribbons` 
						(`user_id`, `story_id`, `last_read_comment_id`, `updated_at`) 
					VALUES " . implode(', ', $valuesPlaceholders) . "
					ON DUPLICATE KEY UPDATE 
						`last_read_comment_id` = GREATEST(`last_read_comment_id`, VALUES(`last_read_comment_id`)),
						`updated_at` = NOW()";

			$this->db->query($sql, $values);
			
		} catch (\Exception $e) {
			if ($this->logger) {
				$this->logger->error("ReadRibbon::syncForStories failed: " . $e->getMessage());
			}
		}
	}
}