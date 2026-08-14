<?php

declare(strict_types=1);

namespace App\Modules\Collections\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

/**
 * Модель связи "коллекция ↔ статья" с порядком.
 */
class CollectionItem extends Model
{
    protected string $table = 'collection_items';

    // В этой таблице нет deleted_at и updated_at
    protected bool $useSoftDeletes = false;
    protected bool $useTimestamps = false;

    protected array $fillable = [
        'collection_id', 'story_id', 'position'
    ];

    public function __construct(Database $db, Logger $logger)
    {
        parent::__construct($db, $logger);
    }

    /**
     * Добавить статью в коллекцию (в конец).
     * 
     * @return int ID созданной записи или 0 если статья уже в коллекции
     */
    public function addStory(int $collectionId, int $storyId): int
    {
        // Проверка: статья уже в коллекции?
        if ($this->isStoryInCollection($collectionId, $storyId)) {
            return 0;
        }

        // Получаем следующую позицию
        $maxPosition = (int) $this->db->fetchColumn(
            "SELECT COALESCE(MAX(position), 0) FROM {$this->table} WHERE collection_id = ?",
            [$collectionId]
        );

        return (int) $this->create([
            'collection_id' => $collectionId,
            'story_id'      => $storyId,
            'position'      => $maxPosition + 1,
        ]);
    }

	/**
	 * Удалить статью из коллекции.
	 */
	public function removeStory(int $collectionId, int $storyId): bool
	{
		$deleted = $this->db->execute(
			"DELETE FROM {$this->table} WHERE collection_id = ? AND story_id = ?",
			[$collectionId, $storyId]
		) > 0;

		// 🆕 Нормализуем позиции после удаления
		if ($deleted) {
			$this->normalizePositions($collectionId);
		}

		return $deleted;
	}

    /**
     * Проверить, находится ли статья в коллекции.
     */
    public function isStoryInCollection(int $collectionId, int $storyId): bool
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM {$this->table} WHERE collection_id = ? AND story_id = ?",
            [$collectionId, $storyId]
        ) > 0;
    }

    /**
     * Получить оглавление коллекции с данными статей.
     * 
     * Возвращает статьи в порядке position с метаданными.
     */
    public function getCollectionStories(int $collectionId): array
    {
        $sql = "SELECT 
                    ci.position,
                    s.id as story_id,
                    s.title,
                    s.slug,
                    s.cover_image,
					s.description_text,
                    s.reading_time,
                    s.comments_count,
                    s.score,
                    s.created_at as story_created_at,
                    u.username as author_name
                FROM {$this->table} ci
                JOIN stories s ON ci.story_id = s.id
                JOIN users u ON s.user_id = u.id
                WHERE ci.collection_id = :collection_id
                  AND s.deleted_at IS NULL
                  AND s.status = 'published'
                ORDER BY ci.position ASC";

        return $this->db->fetchAll($sql, ['collection_id' => $collectionId]);
    }

    /**
     * Получить коллекции, в которых есть данная статья.
     * Используется на странице статьи для навигации.
     * 
     * @return array [{collection_id, collection_title, collection_slug, position, total}]
     */
    public function getCollectionsForStory(int $storyId): array
    {
        $sql = "SELECT 
                    ci.collection_id,
                    ci.position,
                    c.title as collection_title,
                    c.slug as collection_slug,
                    c.author_id,
                    c.stories_count as total,
                    u.username as author_name
                FROM {$this->table} ci
                JOIN collections c ON ci.collection_id = c.id
                JOIN users u ON c.author_id = u.id
                WHERE ci.story_id = :story_id
                  AND c.deleted_at IS NULL
                  AND c.is_public = 1
                ORDER BY c.created_at ASC";

        return $this->db->fetchAll($sql, ['story_id' => $storyId]);
    }

	/**
	 * Получить предыдущую и следующую статьи в коллекции.
	 * 
	 * Устойчив к пропускам в позициях: ищет БЛИЖАЙШУЮ статью
	 * с меньшей/большей позицией, а не строго position ± 1.
	 * 
	 * @return array{prev: ?array, next: ?array}
	 */
	public function getPrevNextStories(int $collectionId, int $storyId): array
	{
		// Получаем текущую позицию
		$current = $this->db->fetchOne(
			"SELECT position FROM {$this->table} 
			 WHERE collection_id = ? AND story_id = ?",
			[$collectionId, $storyId]
		);

		if (!$current) {
			return ['prev' => null, 'next' => null];
		}

		$position = (int) $current['position'];

		$selectSql = "SELECT 
						ci.position,
						s.id as story_id,
						s.title,
						s.slug
					FROM {$this->table} ci
					JOIN stories s ON ci.story_id = s.id
					WHERE ci.collection_id = :collection_id
					  AND s.deleted_at IS NULL
					  AND s.status = 'published'";

		// 🆕 Ищем ближайшую статью с МЕНЬШЕЙ позицией (prev)
		$prev = $this->db->fetchOne(
			$selectSql . " AND ci.position < :position
			ORDER BY ci.position DESC
			LIMIT 1",
			[
				'collection_id' => $collectionId,
				'position'      => $position,
			]
		);

		// 🆕 Ищем ближайшую статью с БОЛЬШЕЙ позицией (next)
		$next = $this->db->fetchOne(
			$selectSql . " AND ci.position > :position
			ORDER BY ci.position ASC
			LIMIT 1",
			[
				'collection_id' => $collectionId,
				'position'      => $position,
			]
		);

		return ['prev' => $prev, 'next' => $next];
	}

	/**
	 * Изменить порядок статей (drag-and-drop).
	 */
	public function reorder(int $collectionId, array $orderedStoryIds): bool
	{
		try {
			$this->db->beginTransaction();

			foreach ($orderedStoryIds as $index => $storyId) {
				$this->db->execute(
					"UPDATE {$this->table} 
					 SET position = ? 
					 WHERE collection_id = ? AND story_id = ?",
					[$index + 1, $collectionId, (int) $storyId]
				);
			}

			$this->db->commit();
			return true;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			if ($this->logger) {
				$this->logger->error("Reorder failed: " . $e->getMessage());
			}
			return false;
		}
	}
	
	/**
	 * Нормализовать позиции после удаления статьи.
	 * 
	 * Пересчитывает позиции так, чтобы они были последовательными: 1, 2, 3...
	 * Вызывается автоматически после removeStory().
	 */
	public function normalizePositions(int $collectionId): void
	{
		// Получаем все статьи коллекции в текущем порядке
		$items = $this->db->fetchAll(
			"SELECT id FROM {$this->table} 
			 WHERE collection_id = ? 
			 ORDER BY position ASC, id ASC",
			[$collectionId]
		);

		if (empty($items)) {
			return;
		}

		// Обновляем позиции последовательно
		try {
			$this->db->beginTransaction();
			
			foreach ($items as $index => $item) {
				$this->db->execute(
					"UPDATE {$this->table} SET position = ? WHERE id = ?",
					[$index + 1, (int) $item['id']]
				);
			}
			
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			if ($this->logger) {
				$this->logger->error("normalizePositions failed: " . $e->getMessage());
			}
		}
	}
}