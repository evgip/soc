<?php

declare(strict_types=1);

namespace App\Modules\Stories\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

class StoryView extends Model
{
    protected string $table = 'story_views';

    protected array $fillable = [
        'user_id',
        'story_id',
        'read_seconds',
    ];

    public function __construct(Database $db, Logger $logger)
    {
        parent::__construct($db, $logger);
    }

	/**
	 * Увеличить время чтения статьи (UPSERT).
	 * 
	 * Использует два разных именованных параметра (:seconds1 и :seconds2),
	 * так как PDO не позволяет переиспользовать один параметр в одном запросе.
	 * 
	 * @param int $userId ID пользователя
	 * @param int $storyId ID статьи
	 * @param int $seconds Сколько секунд добавить к счётчику
	 */
	public function trackReadTime(int $userId, int $storyId, int $seconds): void
	{
		if ($userId <= 0 || $storyId <= 0 || $seconds <= 0) {
			return;
		}

		try {
			$this->db->query("
				INSERT INTO `story_views` 
					(`user_id`, `story_id`, `read_seconds`, `created_at`, `updated_at`)
				VALUES 
					(:user_id, :story_id, :seconds1, NOW(), NOW())
				ON DUPLICATE KEY UPDATE 
					`read_seconds` = `read_seconds` + :seconds2,
					`updated_at` = NOW()
			", [
				'user_id'  => $userId,
				'story_id' => $storyId,
				'seconds1' => $seconds,  // ← для VALUES
				'seconds2' => $seconds,  // ← для ON DUPLICATE KEY UPDATE
			]);
		} catch (\Exception $e) {
			$this->logger?->error("StoryView::trackReadTime failed", [
				'user_id'  => $userId,
				'story_id' => $storyId,
				'seconds'  => $seconds,
				'error'    => $e->getMessage(),
			]);
		}
	}

    public function getUserTopTags(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) {
            return [];
        }

        $sql = "
            SELECT 
                t.id as tag_id,
                t.slug,
                t.name,
                SUM(sv.read_seconds) as total_read_time,
                COUNT(DISTINCT sv.story_id) as stories_read
            FROM `story_views` sv
            JOIN `taggings` tg ON sv.story_id = tg.story_id
            JOIN `tags` t ON tg.tag_id = t.id
            WHERE sv.user_id = :user_id
            GROUP BY t.id, t.slug, t.name
            ORDER BY total_read_time DESC, stories_read DESC
            LIMIT :limit
        ";

        $stmt = $this->db->query($sql, ['user_id' => $userId, 'limit' => $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getViewedStoryIds(int $userId, int $limit = 100): array
    {
        if ($userId <= 0) {
            return [];
        }

        $sql = "
            SELECT `story_id` 
            FROM `story_views` 
            WHERE `user_id` = :user_id 
            ORDER BY `updated_at` DESC 
            LIMIT :limit
        ";

        $stmt = $this->db->query($sql, ['user_id' => $userId, 'limit' => $limit]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }
}