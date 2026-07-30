<?php

namespace App\Modules\Tags\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

class Tag extends Model
{
    protected string $table = 'tags';
    
    protected array $fillable = [
        'name',
        'slug', 
        'description',
        'is_media',
        'category_id',
        'hotness_mod'
    ];

    /**
     * Получить все теги, отсортированные по категориям Lobsters
     */
    public function getAllTags(bool $withDeleted = false): array
    {
        // Если $withDeleted = true, условие WHERE будет пустым. 
        // Если false (по умолчанию), добавится фильтрация удаленных записей.
        $whereClause = $withDeleted ? '' : 'WHERE t.deleted_at IS NULL';

        $sql = "SELECT t.id, t.name, t.slug, t.description, t.hotness_mod, t.category_id, t.is_media, t.deleted_at,
                       c.name as category_name, c.slug as category_slug,
                       COUNT(tg.story_id) as stories_count
                FROM {$this->table} t
                LEFT JOIN categories c ON t.category_id = c.id
                LEFT JOIN taggings tg ON t.id = tg.tag_id
                {$whereClause}
                GROUP BY t.id, t.slug, t.description, t.category_id, 
                         c.name, c.slug, t.is_media
                ORDER BY t.slug ASC";
     
        return $this->db->fetchAll($sql);
    }

    /**
     * Check if a tag string slug already exists matching another ID (for safe edits)
     */
    public function exists(string $tagSlug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `tags` WHERE `slug` = :slug";
        $params = ['slug' => trim($tagSlug)];

        if ($excludeId !== null) {
            $sql .= " AND `id` != :id";
            $params['id'] = $excludeId;
        }

        return (int)$this->db->fetchColumn($sql, $params) > 0;
    }
    
    /**
     * Получить тег по его slug (полю `slug`).
     * 
     * @param string $tagSlug Slug тега (например, 'php')
     * @return array|null Данные тега или null, если не найден
     */
    public function getBySlug(string $tagSlug): ?array
    {
        $tagSlug = mb_strtolower(trim($tagSlug));
        
        if ($tagSlug === '') {
            return null;
        }
        
        $sql = "SELECT * FROM `tags` WHERE `slug` = :slug LIMIT 1";
        
        return $this->db->fetchOne($sql, ['slug' => $tagSlug]);
    }
    
    /**
     * Получить полную информацию о тегах по их ID.
     * 
     * Возвращает ассоциативный массив: id => ['name' => ..., 'slug' => ...]
     * Используется когда нужны и название, и URL-слаг тега.
     * 
     * @param array $tagIds Массив ID тегов
     * @return array Ассоциативный массив с полной информацией
     */
    public function getDetailsByIds(array $tagIds): array
    {
        if (empty($tagIds)) {
            return [];
        }
        
        // Убираем дубликаты и приводим к int
        $tagIds = array_unique(array_map('intval', $tagIds));
        
        // Создаем плейсхолдеры для IN clause
        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        
        $sql = "SELECT id, name, slug FROM `{$this->table}` WHERE id IN ({$placeholders})";
        
        // Используем prepare() для работы с позиционными параметрами
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($tagIds));
        
        // Создаем ассоциативный массив id => данные
        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result[(int) $row['id']] = [
                'name' => $row['name'],
                'slug' => $row['slug']
            ];
        }
        
        return $result;
    }
    
    // =========================================================================
    // МЕТОДЫ ДЛЯ РАБОТЫ С ID (ИСПОЛЬЗУЮТСЯ В SUGGESTION SERVICE)
    // =========================================================================
    
    /**
     * Получить имена тегов по их ID.
     * 
     * Используется для отображения названий тегов вместо ID.
     * Возвращает массив названий в том же порядке, что и входные ID.
     * 
     * @param array $tagIds Массив ID тегов
     * @return array Массив названий тегов (в том же порядке)
     */
    public function getNamesByIds(array $tagIds): array
    {
        if (empty($tagIds)) {
            return [];
        }
        
        // Убираем дубликаты и приводим к int
        $tagIds = array_unique(array_map('intval', $tagIds));
        
        // Создаем плейсхолдеры для IN clause
        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        
        $sql = "SELECT id, name FROM `{$this->table}` WHERE id IN ({$placeholders})";
        
        // Используем prepare() для работы с позиционными параметрами
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($tagIds));
        
        // Создаем ассоциативный массив id => name
        $namesById = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $namesById[(int) $row['id']] = $row['name'];
        }
        
        // Возвращаем названия в том же порядке, что и входные ID
        $result = [];
        foreach ($tagIds as $id) {
            if (isset($namesById[$id])) {
                $result[] = $namesById[$id];
            }
        }
        
        return $result;
    }
}