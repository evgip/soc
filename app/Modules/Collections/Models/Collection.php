<?php

declare(strict_types=1);

namespace App\Modules\Collections\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

/**
 * Модель коллекции (серии статей).
 * 
 * Коллекция — это упорядоченный набор статей одного автора,
 * объединённых общей темой (например, "Песни Миларепы").
 */
class Collection extends Model
{
    protected string $table = 'collections';

    protected array $fillable = [
        'author_id', 'title', 'slug', 'description',
        'cover_image', 'is_public', 'stories_count', 'deleted_at'
    ];

    public function __construct(Database $db, Logger $logger)
    {
        parent::__construct($db, $logger);
    }

    /**
     * Создать новую коллекцию.
     * 
     * @return int ID созданной коллекции
     */
    public function createCollection(array $data): int
    {
        return (int) $this->create([
            'author_id'   => (int) $data['author_id'],
            'title'       => trim((string) $data['title']),
            'slug'        => (string) $data['slug'],
            'description' => $data['description'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'is_public'   => (int) ($data['is_public'] ?? 1),
        ]);
    }

    /**
     * Найти коллекцию по slug с учётом автора.
     */
    public function findByAuthorAndSlug(int $authorId, string $slug): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} 
             WHERE author_id = :author_id AND slug = :slug AND deleted_at IS NULL",
            ['author_id' => $authorId, 'slug' => $slug]
        );
    }

    /**
     * Проверить уникальность slug для автора.
     */
    public function slugExists(int $authorId, string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} 
                WHERE author_id = :author_id AND slug = :slug AND deleted_at IS NULL";
        $params = ['author_id' => $authorId, 'slug' => $slug];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        return (int) $this->db->fetchColumn($sql, $params) > 0;
    }

    /**
     * Все публичные коллекции пользователя.
     */
    public function getByAuthor(int $authorId): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE author_id = :author_id AND deleted_at IS NULL
                ORDER BY created_at DESC";

        return $this->db->fetchAll($sql, ['author_id' => $authorId]);
    }

    /**
     * Коллекции с данными об авторе (для глобальных списков).
     */
    public function getPublicWithAuthors(int $limit, int $offset): array
    {
        $sql = "SELECT c.*, u.username as author_name, up.avatar as author_avatar
                FROM {$this->table} c
                JOIN users u ON c.author_id = u.id
                LEFT JOIN user_profiles up ON u.id = up.user_id
                WHERE c.is_public = 1 AND c.deleted_at IS NULL
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset";

        return $this->db->fetchAll($sql, ['limit' => $limit, 'offset' => $offset]);
    }

    /**
     * Подсчитать публичные коллекции.
     */
    public function countPublic(): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM {$this->table} WHERE is_public = 1 AND deleted_at IS NULL"
        );
    }

    /**
     * Обновить счётчик статей (вызывается после добавления/удаления).
     */
    public function updateStoriesCount(int $collectionId): void
    {
        $this->db->execute(
            "UPDATE {$this->table} 
             SET stories_count = (
                 SELECT COUNT(*) FROM collection_items WHERE collection_id = ?
             )
             WHERE id = ?",
            [$collectionId, $collectionId]
        );
    }

}