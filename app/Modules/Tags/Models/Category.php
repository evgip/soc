<?php

namespace App\Modules\Tags\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;
use App\Modules\Stories\Repositories\StoryRepository;

class Category extends Model
{
    protected string $table = 'categories';

    protected array $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
    ];

    /**
     * Получить все категории с количеством тегов (для публичной страницы /categories)
     */
    public function getAllWithTagsCount(): array
    {
        $sql = "SELECT c.id, c.name, c.slug, c.description, c.sort_order,
                       COUNT(t.id) as tags_count
                FROM {$this->table} c
                LEFT JOIN tags t ON t.category_id = c.id AND t.deleted_at IS NULL
                GROUP BY c.id, c.name, c.slug, c.description, c.sort_order
                ORDER BY c.sort_order ASC, c.name ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Получить истории, связанные с тегами конкретной категории по slug
     */
    public function getStoriesBySlug(string $slug, int $limit, int $offset, array $excludeTagIds = []): ?array
    {
        $category = $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE slug = :slug LIMIT 1",
            ['slug' => $slug]
        );

        if (!$category) {
            return null;
        }

        // Используем StoryRepository для получения историй
        $repo = new StoryRepository($this->db);
        
        $repo->withAuthor()
             ->withAvatar()
             ->withTags()
             ->addWhere('t.category_id = :category_id', ['category_id' => $category['id']])
             ->addWhere('t.deleted_at IS NULL')
             ->setOrderBy('s.created_at DESC')
             ->paginate($limit, $offset);

        // Исключаем истории с тегами из черного списка пользователя
        // Это исправляет баг с невалидным SQL из оригинального кода
        if (!empty($excludeTagIds)) {
            $inData = $this->db->buildInClause($excludeTagIds, 'exclude_tag');
            $repo->addWhere(
                "s.id NOT IN (SELECT DISTINCT story_id FROM taggings WHERE tag_id IN ({$inData['clause']}))",
                $inData['bindings']
            );
        }

        $category['stories'] = $repo->get();
        return $category;
    }

    /**
     * Получить общее количество историй в категории
     */
    public function getStoriesCountBySlug(string $slug, array $excludeTagIds = []): int
    {
        $category = $this->db->fetchOne(
            "SELECT id FROM {$this->table} WHERE slug = :slug LIMIT 1",
            ['slug' => $slug]
        );

        if (!$category) {
            return 0;
        }

        $repo = new StoryRepository($this->db);
        
        // Подключаем tags, чтобы можно было фильтровать по category_id.
        // Для count() GROUP BY будет проигнорирован, но LEFT JOIN останется.
        $repo->withTags() 
             ->addWhere('t.category_id = :category_id', ['category_id' => $category['id']])
             ->addWhere('t.deleted_at IS NULL');

        if (!empty($excludeTagIds)) {
            $inData = $this->db->buildInClause($excludeTagIds, 'exclude_tag');
            $repo->addWhere(
                "s.id NOT IN (SELECT DISTINCT story_id FROM taggings WHERE tag_id IN ({$inData['clause']}))",
                $inData['bindings']
            );
        }

        return $repo->count();
    }

    /**
     * Получить все категории с количеством тегов для админки
     */
    public function getAdminCategoriesList(): array
    {
        $sql = "SELECT c.*, 
                       COUNT(t.id) as tags_count
                FROM {$this->table} c
                LEFT JOIN tags t ON t.category_id = c.id AND t.deleted_at IS NULL
                GROUP BY c.id
                ORDER BY c.sort_order ASC, c.name ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Получить все категории (простой список для select)
     */
    public function getAllOrdered(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, slug FROM {$this->table} ORDER BY sort_order ASC, name ASC"
        );
    }

    /**
     * Проверить уникальность slug
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE slug = :slug";
        $params = ['slug' => $slug];

        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        return (int)$this->db->fetchColumn($sql, $params) > 0;
    }

    /**
     * Создать новую категорию.
     * Используем стандартный метод create() из базовой модели Model.
     * Он автоматически отфильтрует поля по $fillable и защитит от Mass Assignment.
     */
    public function createCategory(array $data): int
    {
        return $this->create($data);
    }

    /**
     * Обновить категорию.
     * Используем стандартный метод update() из базовой модели Model.
     */
    public function updateCategory(int $id, array $data): bool
    {
        return $this->update($id, $data);
    }

    /**
     * Удалить категорию.
     * Если в таблице categories есть колонка deleted_at, используйте $this->softDelete($id)
     * Если нужно физическое удаление, используйте $this->forceDelete($id).
     */
    public function deleteCategory(int $id): bool
    {
        return $this->softDelete($id);
    }

    /**
     * Проверить, есть ли теги в категории (для защиты от удаления)
     */
    public function hasTags(int $categoryId): bool
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM tags WHERE category_id = :id AND deleted_at IS NULL",
            ['id' => $categoryId]
        ) > 0;
    }
}