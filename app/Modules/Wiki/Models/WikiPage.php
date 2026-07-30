<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

/**
 * Модель Wiki страницы.
 *
 * Отвечает за работу с таблицей wiki_pages.
 */
class WikiPage extends Model
{
    protected string $table = 'wiki_pages';

    protected array $fillable = [
        'tag_id',
        'title',
        'slug',
        'content',
        'rendered_content',
        'author_id',
        'is_primary',
        'status',
        'view_count',
        'deleted_at'
    ];

    /**
     * Получить все опубликованные wiki страницы
     */
    public function getAllPublished(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT wp.*, 
                       u.username as author_name,
                       t.name as tag_name,
                       t.slug as tag_slug
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                LEFT JOIN tags t ON wp.tag_id = t.id
                WHERE wp.status = 'published' 
                  AND wp.deleted_at IS NULL
                ORDER BY wp.updated_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Получить страницу по slug (опционально в контексте тега)
     */
    public function getBySlug(string $slug, ?int $tagId = null): ?array
    {
        $sql = "SELECT wp.*, 
                       u.username as author_name,
                       t.name as tag_name,
                       t.slug as tag_slug
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                LEFT JOIN tags t ON wp.tag_id = t.id
                WHERE wp.slug = :slug 
                  AND wp.deleted_at IS NULL";

        $params = ['slug' => $slug];

        if ($tagId !== null) {
            $sql .= " AND wp.tag_id = :tag_id";
            $params['tag_id'] = $tagId;
        }

        $sql .= " LIMIT 1";

        return $this->db->fetchOne($sql, $params);
    }

    /**
     * Получить wiki страницы для тега
     */
    public function getForTag(int $tagId): array
    {
        $sql = "SELECT wp.*, u.username as author_name
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                WHERE wp.tag_id = :tag_id 
                AND wp.status = 'published'
                ORDER BY wp.is_primary DESC, wp.title ASC";

        return $this->db->fetchAll($sql, ['tag_id' => $tagId]);
    }

    /**
     * Найти страницу включая удалённые (soft deleted)
     */
    public function findWithDeleted(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        
        $sql = "SELECT wp.*, u.username as author_name
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                WHERE wp.id = :id
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Получить основную страницу тега
     */
    public function getPrimaryForTag(int $tagId): ?array
    {
        $sql = "SELECT wp.*, u.username as author_name
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                WHERE wp.tag_id = :tag_id 
                  AND wp.is_primary = 1
                  AND wp.status = 'published'
                  AND wp.deleted_at IS NULL
                LIMIT 1";

        return $this->db->fetchOne($sql, ['tag_id' => $tagId]);
    }

    /**
     * Увеличить счетчик просмотров
     */
    public function incrementViewCount(int $pageId): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET view_count = view_count + 1 WHERE id = :id",
            ['id' => $pageId]
        );
    }

    /**
     * Проверить существование slug в пределах тега
     */
    public function slugExists(string $slug, int $tagId, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} 
                WHERE slug = :slug AND tag_id = :tag_id";
        
        $params = [
            'slug' => $slug,
            'tag_id' => $tagId
        ];

        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        return (int)$this->db->fetchColumn($sql, $params) > 0;
    }

    /**
     * Поиск по wiki страницам (глобальный)
     */
    public function search(string $query, int $limit = 20): array
    {
        $sql = "SELECT wp.*, u.username as author_name
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                WHERE wp.status = 'published'
                  AND wp.deleted_at IS NULL
                  AND (wp.title LIKE :query OR wp.content LIKE :query)
                ORDER BY wp.updated_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':query', '%' . $query . '%', \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Поиск по wiki страницам тега
     */
    public function searchInTag(int $tagId, string $query, int $limit = 20): array
    {
        $sql = "SELECT wp.*, u.username as author_name
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                WHERE wp.tag_id = :tag_id
                AND wp.status = 'published'
                AND wp.deleted_at IS NULL
                AND (wp.title LIKE :query_title OR wp.content LIKE :query_content)
                ORDER BY wp.updated_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $searchTerm = '%' . $query . '%';
        
        $stmt->bindValue(':tag_id', $tagId, \PDO::PARAM_INT);
        $stmt->bindValue(':query_title', $searchTerm, \PDO::PARAM_STR);
        $stmt->bindValue(':query_content', $searchTerm, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Сбросить флаг is_primary для всех страниц тега
     */
    public function resetPrimaryFlag(int $tagId): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET is_primary = 0 WHERE tag_id = :tag_id",
            ['tag_id' => $tagId]
        );
    }

    /**
     * Получить количество страниц для тега
     */
    public function getCountForTag(int $tagId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM {$this->table} 
             WHERE tag_id = :tag_id 
               AND status = 'published'
               AND deleted_at IS NULL",
            ['tag_id' => $tagId]
        );
    }
    
    /**
     * Восстановить удалённую страницу (soft delete)
     */
    public function restore($id): bool
    {
        $id = (int)$id;
        
        if ($id <= 0) {
            return false;
        }
        
        try {
            $sql = "UPDATE {$this->table} 
                    SET deleted_at = NULL 
                    WHERE id = :id AND deleted_at IS NOT NULL";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
            
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Получить список удалённых страниц
     */
    public function getDeleted(?int $tagId = null, int $limit = 50): array
    {
        $sql = "SELECT wp.*, u.username as author_name, t.slug as tag_slug
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                LEFT JOIN tags t ON wp.tag_id = t.id
                WHERE wp.deleted_at IS NOT NULL";
        
        $params = [];
        
        if ($tagId !== null) {
            $sql .= " AND wp.tag_id = :tag_id";
            $params[':tag_id'] = $tagId;
        }
        
        $sql .= " ORDER BY wp.deleted_at DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, \PDO::PARAM_INT);
        }
        
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить все wiki страницы с информацией о тегах (для админки)
     */
    public function getAllPagesWithTags(int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT wp.*, 
                       t.name as tag_name, 
                       t.slug as tag_slug,
                       u.username as author_name
                FROM {$this->table} wp
                LEFT JOIN tags t ON wp.tag_id = t.id
                LEFT JOIN users u ON wp.author_id = u.id
                ORDER BY wp.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Получить общее количество wiki страниц (включая удалённые)
     */
    public function getTotalPagesCount(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM {$this->table}");
    }

    /**
     * Получить количество удалённых wiki страниц
     */
    public function getDeletedPagesCount(): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM {$this->table} WHERE deleted_at IS NOT NULL"
        );
    }

    /**
     * Мягкое удаление wiki страницы
     */
    public function softDelete($id): bool
    {
        $id = (int)$id;
        
        if ($id <= 0) {
            return false;
        }
        
        try {
            $sql = "UPDATE {$this->table} 
                    SET deleted_at = NOW() 
                    WHERE id = :id AND deleted_at IS NULL";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
            
        } catch (\Throwable $e) {
            return false;
        }
    }
}