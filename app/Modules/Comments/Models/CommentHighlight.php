<?php

declare(strict_types=1);

namespace App\Modules\Comments\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

class CommentHighlight extends Model
{
    protected string $table = 'comment_highlights';

    // У этой таблицы нет deleted_at — отключаем soft delete
    protected bool $useSoftDeletes = false;

    protected array $fillable = [
        'comment_id', 'story_id', 'quoted_text',
        'block_index', 'block_type', 'start_offset', 'end_offset'
    ];

    public function __construct(Database $db, Logger $logger)
    {
        parent::__construct($db, $logger);
    }

    /**
     * Получить все highlights для статьи
     */
    public function getByStory(int $storyId): array
    {
        $sql = "SELECT h.*, 
                       c.comment, c.user_id, c.score, c.created_at as comment_created_at,
                       u.username as author_name
                FROM {$this->table} h
                JOIN comments c ON h.comment_id = c.id
                JOIN users u ON c.user_id = u.id
                WHERE h.story_id = :story_id AND c.deleted_at IS NULL
                ORDER BY h.block_index ASC, h.start_offset ASC";
        
        return $this->db->fetchAll($sql, ['story_id' => $storyId]);
    }

    /**
     * Получить highlight по ID комментария
     */
    public function getByCommentId(int $commentId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE comment_id = :id",
            ['id' => $commentId]
        );
    }

	/**
	 * Сохранить новый highlight
	 */
	public function saveHighlight(array $data): int
	{
		return (int) $this->create([
			'comment_id'   => (int) $data['comment_id'],
			'story_id'     => (int) $data['story_id'],
			'quoted_text'  => (string) $data['quoted_text'],
			'block_index'  => $this->toNullableInt($data['block_index'] ?? null),
			'block_type'   => $this->toNullableString($data['block_type'] ?? null),
			'start_offset' => $this->toNullableInt($data['start_offset'] ?? null),
			'end_offset'   => $this->toNullableInt($data['end_offset'] ?? null),
		]);
	}

	/**
	 * Преобразует пустые строки и невалидные значения в NULL, иначе в int.
	 * Защита от ошибки "Incorrect integer value: '' for column ..."
	 */
	private function toNullableInt(mixed $value): ?int
	{
		if ($value === null || $value === '' || $value === false) {
			return null;
		}
		
		$int = filter_var($value, FILTER_VALIDATE_INT);
		return $int === false ? null : $int;
	}

	/**
	 * Преобразует пустые строки в NULL (для VARCHAR колонок)
	 */
	private function toNullableString(mixed $value): ?string
	{
		if ($value === null || $value === '') {
			return null;
		}
		
		return mb_substr((string)$value, 0, 50);
	}
	
	/**
	 * Получить highlights для массива ID комментариев
	 */
	public function getByCommentIds(array $commentIds): array
	{
		if (empty($commentIds)) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($commentIds), '?'));
		
		$sql = "SELECT comment_id, quoted_text 
				FROM {$this->table} 
				WHERE comment_id IN ({$placeholders})";
		
		return $this->db->fetchAll($sql, $commentIds);
	}
}