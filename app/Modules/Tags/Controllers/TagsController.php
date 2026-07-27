<?php

declare(strict_types=1);

namespace App\Modules\Tags\Controllers;

use App\BaseController;
use App\Modules\Tags\Services\CategoryService;
use App\Modules\Tags\Services\TagFilterService;
use App\Modules\Votes\Models\Vote;

/**
 * Контроллер модуля Tags.
 * Отвечает за отображение категорий тегов и управление фильтрами.
 * Вся бизнес-логика вынесена в сервисы CategoryService и TagFilterService.
 */
class TagsController extends BaseController
{
    // =========================================================================
    // КАТЕГОРИИ ТЕГОВ
    // =========================================================================

    /**
     * Страница со всеми категориями тегов (GET /categories)
     */
    public function index(): void
    {
        $categories = $this->service(CategoryService::class)->getCategoriesWithTagsCount();
        $tagsByCategory = $this->service(CategoryService::class)->getTagsGroupedByCategory();

        $this->render('index', [
            'title' => 'Категории тегов',
            'categories' => $categories,
            'tagsByCategory' => $tagsByCategory,
        ]);
    }

    /**
     * Страница историй, которые прикреплены к тегам конкретной категории
     * (GET /categories/{slug})
     */
    public function categoriesShow(string $slug): void
    {
        $currentPage = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config('constants.pagination.stories_per_page', 15, 'int');

        $data = $this->service(CategoryService::class)->getCategoryWithStories($slug, $currentPage, $perPage);

        if (!$data) {
            $this->redirectWithMessage('/categories', 'Категория не найдена.', 'error');
            return;
        }

        $userContext = $this->getUserContext();
        $canUserDownvote = $this->canUserDownvote($userContext['id']);

        $currentVotes = [];
        if ($userContext['isLoggedIn']) {
            $storyIds = array_column($data['stories'], 'id');
            $voteModel = $this->container->get(Vote::class);
            $currentVotes = $voteModel->getUserVotesForStories($userContext['id'], $storyIds);
        }

        $this->render('categories-show', [
            'title' => e($data['category']['name']),
            'category' => $data['category'],
            'stories' => $data['stories'],
            'currentPage' => $data['currentPage'],
            'totalPages' => $data['totalPages'],
            'newCommentsMap' => $data['newCommentsMap'],
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'canUserDownvote' => $canUserDownvote,
            'currentVotes' => $currentVotes,
        ]);
    }

    // =========================================================================
    // ФИЛЬТРЫ ТЕГОВ
    // =========================================================================

    /**
     * Страница управления фильтрами тегов (GET /filters)
     */
    public function filters(): void
    {
        $userContext = $this->getUserContext();
        $data = $this->service(TagFilterService::class)->getFiltersData($userContext['id']);

        $this->render('filters', [
            'title' => 'Фильтры тегов',
            'filters' => $data['filters'],
            'allTags' => $data['allTags'],
            'request' => $this->request
        ]);
    }

    /**
     * Добавить тег в фильтры (POST /filters/add)
     */
    public function addFilter(): void
    {
        $tagId = (int)$this->request->post('tag_id', 0);

        $userContext = $this->getUserContext();

        $result = $this->service(TagFilterService::class)->addFilter($userContext['id'], $tagId);

        $message = $result['message'] ?? ($result['success'] ? 'Фильтр добавлен' : 'Ошибка добавления фильтра');
        $type = $result['success'] ? 'success' : 'error';

        $this->redirectWithMessage('/filters', $message, $type);
    }

    /**
     * Удалить тег из фильтров (POST /filters/remove)
     */
    public function removeFilter(): void
    {
        $tagId = (int)$this->request->post('tag_id', 0);

        $userContext = $this->getUserContext();

        $result = $this->service(TagFilterService::class)->removeFilter($userContext['id'], $tagId);

        $message = $result['message'] ?? ($result['success'] ? 'Фильтр удалён' : 'Ошибка удаления фильтра');
        $type = $result['success'] ? 'success' : 'error';

        $this->redirectWithMessage('/filters', $message, $type);
    }
}
