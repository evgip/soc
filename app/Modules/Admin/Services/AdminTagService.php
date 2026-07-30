<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Models\Category;
use App\Modules\Stories\Models\Story;
use W3a\Core\Support\Audit;
use W3a\Core\Support\Validator;
use W3a\Core\Database\Database;
use App\Modules\Admin\Exceptions\AdminValidationException;

/**
 * Сервис для административного управления тегами.
 */
class AdminTagService
{
    private Tag $tagModel;
    private Category $categoryModel;
    private Story $storyModel;
    private Audit $audit;
    private Validator $validator;
    private Database $db;

    public function __construct(
        Tag $tagModel,
        Category $categoryModel,
        Story $storyModel,
        Audit $audit,
        Validator $validator,
        Database $db
    ) {
        $this->tagModel = $tagModel;
        $this->categoryModel = $categoryModel;
        $this->storyModel = $storyModel;
        $this->audit = $audit;
        $this->validator = $validator;
        $this->db = $db;
    }

    public function getAllTags(): array
    {
        return $this->tagModel->getAllTags(true);
    }

    public function getTagById(int $tagId): ?array
    {
        return $this->tagModel->find($tagId, withTrashed: true);
    }

    /**
     * Создать новый тег.
     *
     * @throws AdminValidationException Если данные не прошли валидацию
     */
    public function createTag(array $data): int
    {
        $tagName = strtolower(trim($data['name'] ?? ''));
        $tagSlug = strtolower(trim($data['slug'] ?? ''));
        $description = trim($data['description'] ?? '');
        $isMedia = isset($data['is_media']) ? 1 : 0;
        $categoryId = (int)($data['category_id'] ?? 0);

        $this->validator->validate(['slug' => $tagSlug], ['slug' => 'required|min:2']);
        if (!$this->validator->isValid()) {
            throw new AdminValidationException('Slug тега должен содержать не менее 2 символов.');
        }

        $this->validator->validate(['name' => $tagName], ['name' => 'required|min:2']);
        if (!$this->validator->isValid()) {
            throw new AdminValidationException('Название тега должно содержать не менее 2 символов.');
        }

        if ($this->tagModel->exists($tagSlug)) {
            throw new AdminValidationException("Тег '{$tagSlug}' уже присутствует в базе данных.");
        }

        if (!$this->categoryModel->find($categoryId)) {
            throw new AdminValidationException('Выбранная категория не существует.');
        }

        $tagId = $this->tagModel->create([
            'name' => $tagName,
            'slug' => $tagSlug,
            'description' => $description,
            'is_media' => $isMedia,
            'category_id' => $categoryId,
        ]);

        $this->audit->log('admin.tag_created', "Администратор создал новый тег #{$tagSlug}", 'admin');

        return $tagId;
    }

    /**
     * Обновить существующий тег.
     *
     * @throws AdminValidationException Если данные не прошли валидацию или тег не найден
     */
    public function updateTag(int $tagId, array $data): bool
    {
        $tag = $this->tagModel->find($tagId);
        if (!$tag) {
            throw new AdminValidationException('Тег не найден.');
        }

        $tagName = strtolower(trim($data['name'] ?? ''));
        $tagSlug = strtolower(trim($data['slug'] ?? ''));
        $description = trim($data['description'] ?? '');
        $isMedia = isset($data['is_media']) ? 1 : 0;
        $categoryId = (int)($data['category_id'] ?? 0);
        $hotnessMod = (float)($data['hotness_mod'] ?? 0);

        $this->validator->validate(['slug' => $tagSlug], ['slug' => 'required|min:2']);
        if (!$this->validator->isValid()) {
            throw new AdminValidationException('Slug тега должен содержать не менее 2 символов.');
        }

        $this->validator->validate(['name' => $tagName], ['name' => 'required|min:2']);
        if (!$this->validator->isValid()) {
            throw new AdminValidationException('Название тега должно содержать не менее 2 символов.');
        }

        if ($this->tagModel->exists($tagSlug, $tagId)) {
            throw new AdminValidationException("Имя тега '{$tagSlug}' занято другим элементом.");
        }

        if (!$this->categoryModel->find($categoryId)) {
            throw new AdminValidationException('Выбранная категория не существует.');
        }

        $this->tagModel->update($tagId, [
            'name' => $tagName,
            'slug' => $tagSlug,
            'description' => $description,
            'is_media' => $isMedia,
            'category_id' => $categoryId,
            'hotness_mod' => $hotnessMod,
        ]);

        $stmt = $this->db->prepare("SELECT story_id FROM taggings WHERE tag_id = :tag_id");
        $stmt->execute(['tag_id' => $tagId]);
        $storyIds = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'story_id');

        foreach ($storyIds as $storyId) {
            $this->storyModel->recalculateHotness((int)$storyId);
        }

        $this->audit->log('admin.tag_updated', "Администратор изменил параметры тега #{$tagSlug}", 'admin');

        return true;
    }

    public function softDeleteTag(int $id): bool
    {
        return $this->tagModel->delete($id);
    }

    public function restoreTag(int $id): bool
    {
        return $this->tagModel->restore($id);
    }
}