<?php

namespace App\Modules\Tags\Services;

use App\Modules\Tags\Models\Category;
use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Models\TagFilter;
use App\Modules\Stories\Models\ReadRibbon;
use W3a\Core\Auth\Auth;

/**
 * Сервис для работы с категориями тегов.
 * Инкапсулирует бизнес-логику: группировка тегов, пагинация историй по категориям.
 */
class CategoryService
{
    private Category $categoryModel;
    private Tag $tagModel;
    private TagFilter $filterModel;
    private ReadRibbon $readRibbon;

    public function __construct(
        ?Category $categoryModel = null,
        ?Tag $tagModel = null,
        ?TagFilter $filterModel = null,
        ?ReadRibbon $readRibbon = null
    ) {
        $this->categoryModel = $categoryModel ?? new Category();
        $this->tagModel = $tagModel ?? new Tag();
        $this->filterModel = $filterModel ?? new TagFilter();
        $this->readRibbon = $readRibbon ?? new ReadRibbon();
    }

    /**
     * Получить все категории с количеством тегов для страницы /categories
     */
    public function getCategoriesWithTagsCount(): array
    {
        return $this->categoryModel->getAllWithTagsCount();
    }

    /**
     * Получить все теги, сгруппированные по категориям.
     * @return array Массив вида [category_id => [tag1, tag2, ...]]
     */
    public function getTagsGroupedByCategory(): array
    {
        $allTags = $this->tagModel->getAllTags(false);

        $tagsByCategory = [];
        foreach ($allTags as $tag) {
            $catId = $tag['category_id'] ?? 0;
            if (!isset($tagsByCategory[$catId])) {
                $tagsByCategory[$catId] = [];
            }
            $tagsByCategory[$catId][] = $tag;
        }

        return $tagsByCategory;
    }

    /**
     * Получить данные для страницы категории: категория с историями и пагинацией.
     */
    public function getCategoryWithStories(string $slug, int $currentPage, int $perPage): ?array
    {
        $offset = ($currentPage - 1) * $perPage;

        // Получаем фильтры пользователя
        $excludeTagIds = $this->getUserExcludeTagIds();

        // Получаем общее количество историй для пагинации
        $totalStories = $this->categoryModel->getStoriesCountBySlug($slug, $excludeTagIds);
        $totalPages = (int)ceil($totalStories / $perPage);

        // Получаем категорию с историями
        $category = $this->categoryModel->getStoriesBySlug($slug, $perPage, $offset, $excludeTagIds);

        if (!$category) {
            return null;
        }

        // Подсчёт новых комментариев для каждой истории
        $newCommentsMap = $this->getNewCommentsForStories($category['stories'] ?? []);

        return [
            'category' => $category,
            'stories' => $category['stories'],
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'newCommentsMap' => $newCommentsMap,
        ];
    }

    /**
     * Получить ID тегов, которые пользователь исключил из ленты.
     */
	private function getUserExcludeTagIds(): array
	{
		if (!Auth::check()) {
			return [];
		}

		return $this->filterModel->getFilteredTagIds(Auth::id());
	}

    /**
     * Подсчитать количество новых комментариев для списка историй.
     */
	private function getNewCommentsForStories(array $stories): array
	{
		if (!Auth::check() || empty($stories)) {
			return [];
		}

		$storyIds = array_column($stories, 'id');

		return $this->readRibbon->getNewCommentsCounts(
			Auth::id(),
			array_map('intval', $storyIds)
		);
	}
}