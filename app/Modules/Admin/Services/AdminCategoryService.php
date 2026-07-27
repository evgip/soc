<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Tags\Models\Category;
use W3a\Core\Audit;
use App\Modules\Admin\Exceptions\AdminValidationException;

/**
 * Сервис для административного управления категориями тегов.
 */
class AdminCategoryService
{
    private Category $categoryModel;
    private Audit $audit;

    public function __construct(Category $categoryModel, Audit $audit)
    {
        $this->categoryModel = $categoryModel;
        $this->audit = $audit;
    }

    public function getCategoriesList(): array
    {
        return $this->categoryModel->getAdminCategoriesList();
    }

    public function getCategoryById(int $categoryId): ?array
    {
        return $this->categoryModel->find($categoryId, withTrashed: true);
    }

    /**
     * @throws AdminValidationException
     */
    public function createCategory(array $data): int
    {
        $name = trim($data['name'] ?? '');
        $slug = strtolower(trim($data['slug'] ?? ''));
        $description = trim($data['description'] ?? '');
        $sortOrder = (int)($data['sort_order'] ?? 0);

        if (strlen($name) < 2) {
            throw new AdminValidationException('Название категории должно содержать не менее 2 символов.');
        }

        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new AdminValidationException('Slug должен содержать только латиницу в нижнем регистре, цифры и дефис.');
        }

        if ($this->categoryModel->slugExists($slug)) {
            throw new AdminValidationException("Категория с slug '{$slug}' уже существует.");
        }

        $categoryId = $this->categoryModel->createCategory([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'sort_order' => $sortOrder,
        ]);

        $this->audit->log('admin.category_created', "Администратор создал категорию '{$name}' (slug: {$slug})", 'admin');

        return $categoryId;
    }

    /**
     * @throws AdminValidationException
     */
    public function updateCategory(int $categoryId, array $data): bool
    {
        $category = $this->categoryModel->find($categoryId);
        if (!$category) {
            throw new AdminValidationException('Категория не найдена.');
        }

        $name = trim($data['name'] ?? '');
        $slug = strtolower(trim($data['slug'] ?? ''));
        $description = trim($data['description'] ?? '');
        $sortOrder = (int)($data['sort_order'] ?? 0);

        if (strlen($name) < 2) {
            throw new AdminValidationException('Название категории должно содержать не менее 2 символов.');
        }

        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new AdminValidationException('Slug должен содержать только латиницу в нижнем регистре, цифры и дефис.');
        }

        if ($this->categoryModel->slugExists($slug, $categoryId)) {
            throw new AdminValidationException("Slug '{$slug}' уже используется другой категорией.");
        }

        $this->categoryModel->updateCategory($categoryId, [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'sort_order' => $sortOrder,
        ]);

        $this->audit->log('admin.category_updated', "Администратор обновил категорию '{$name}' (ID: {$categoryId})", 'admin');

        return true;
    }

    /**
     * @throws AdminValidationException
     */
    public function deleteCategory(int $categoryId): bool
    {
        $category = $this->categoryModel->find($categoryId);
        if (!$category) {
            throw new AdminValidationException('Категория не найдена.');
        }

        if ($this->categoryModel->hasTags($categoryId)) {
            throw new AdminValidationException('Нельзя удалить категорию, содержащую теги. Сначала перенесите теги в другую категорию.');
        }

        $this->categoryModel->deleteCategory($categoryId);

        $this->audit->log('admin.category_deleted', "Администратор удалил категорию '{$category['name']}' (ID: {$categoryId})", 'admin');

        return true;
    }
}