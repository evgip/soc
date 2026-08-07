<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use W3a\Core\Foundation\Container;
use W3a\Core\Auth\Auth;
use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Models\ReadRibbon;
use App\Modules\Stories\Services\RankingService;
use App\Modules\Tags\Models\TagFilter;
use App\Modules\Muted\Services\MuteService;

/**
 * Сервис для фильтрации и получения списков статей (Medium-стиль).
 */
class StoryFilterService
{
    private Story $storyModel;
    private Container $container;
    private MuteService $muteService;
    private RankingService $rankingService;

    /**
     * Конструктор с инъекцией зависимостей.
     * 
     * @param Story $storyModel Модель статей
     * @param Container $container DI-контейнер
     * @param MuteService|null $muteService Сервис для работы с игнорируемыми пользователями
     * @param RankingService|null $rankingService Сервис для расчёта рейтингов
     */
    public function __construct(
        Story $storyModel, 
        Container $container, 
        ?MuteService $muteService = null, 
        ?RankingService $rankingService = null
    ) {
        $this->storyModel = $storyModel;
        $this->container = $container;
        $this->muteService = $muteService;
        $this->rankingService = $rankingService ?? new RankingService();
    }

    /**
     * Получает ленту статей с учётом всех фильтров.
     *
     * @param int $perPage Количество на страницу
     * @param int $offset Смещение
     * @param string $tagslug Фильтр по тегу
     * @param string $sort Сортировка (hot, new, top)
     * @param string $author Фильтр по автору
     * @return array Массив статей
     */
    public function getFilteredStories(
        int $perPage,
        int $offset,
        string $tagslug = '',
        string $sort = 'hot',
        string $author = ''
    ): array {
        $showDeleted = Auth::isAdmin();
        $excludeTagIds = $this->getUserExcludedTags();
        $mutedUserIds = $this->getMutedUserIds();

        return $this->storyModel->getFeed(
            $perPage, 
            $offset, 
            $tagslug, 
            $showDeleted, 
            $excludeTagIds, 
            $sort, 
            $author, 
            $mutedUserIds
        );
    }

    /**
     * Получает общее количество статей с учётом фильтров.
     */
    public function getTotalCount(
        string $tagname = '',
        string $author = ''
    ): int {
        $excludeTagIds = $this->getUserExcludedTags();
        $mutedUserIds = $this->getMutedUserIds();
        return $this->storyModel->getTotalCount($tagname, $excludeTagIds, $author, $mutedUserIds);
    }

    /**
     * Получает комментарии для статьи в виде дерева с сортировкой по Вильсону
     */
    public function getCommentsTree(int $storyId): array
    {
        $mutedUserIds = $this->getMutedUserIds();

        $flatComments = $this->storyModel->getCommentsForStory($storyId, $mutedUserIds);

        // Вычисляем confidence_score только если его нет в БД
        foreach ($flatComments as &$comment) {
            if (empty($comment['confidence_score'])) {
                $comment['confidence_score'] = $this->rankingService->wilsonScore(
                    (int)$comment['score'],
                    (int)$comment['flag_count']
                );
            }
        }
        unset($comment);

        // Строим дерево (уже отсортировано благодаря SQL ORDER BY)
        $commentsTree = [];
        foreach ($flatComments as $comment) {
            $parentId = $comment['parent_id'] ?? 0;
            $commentsTree[$parentId][] = $comment;
        }

        return $commentsTree;
    }

    /**
     * Получает ID тегов, которые пользователь исключил из ленты.
     *
     * @return array Массив ID тегов
     */
    public function getUserExcludedTags(): array
    {
        if (!Auth::check()) {
            return [];
        }

        $filterModel = $this->container->get(TagFilter::class);
        return $filterModel->getFilteredTagIds(Auth::id());
    }

    /**
     * Получает количество новых комментариев для списка статей.
     *
     * @param array $storyIds Массив ID статей
     * @return array Массив [story_id => count]
     */
    public function getNewCommentsCounts(array $storyIds): array
    {
        if (!Auth::check() || empty($storyIds)) {
            return [];
        }

        $readRibbon = $this->container->get(ReadRibbon::class);
        $mutedUserIds = $this->getMutedUserIds();

        return $readRibbon->getNewCommentsCounts(
            Auth::id(),
            array_map('intval', $storyIds),
            $mutedUserIds
        );
    }

    /**
     * Получает одну статью с информацией об авторе.
     */
    public function getStoryWithAuthor(int $storyId): ?array
    {
        $showDeleted = Auth::isAdmin();
        return $this->storyModel->getSingleWithAuthor($storyId, $showDeleted);
    }

    /**
     * Подготовить данные для Open Graph мета-тегов.
     * 
     * @param array $story Данные статьи (уже загруженные)
     * @return array Массив с ключами: title, description, image, author_name, author_url
     */
    public function getStoryOpenGraphData(array $story): array
    {
        if (empty($story)) {
            return [];
        }

        // Описание: берем из description_text (чистый текст для поиска)
        $description = '';
        if (!empty($story['description_text'])) {
            $description = mb_substr($story['description_text'], 0, 200);
            if (mb_strlen($story['description_text']) > 200) {
                $description .= '...';
            }
        } else {
            $description = (int)$story['comments_count'] . ' комментариев';
        }

        // Изображение: дефолтное (можно добавить логику для первой картинки из JSON позже)
        $image = null;

        return [
            'title' => $story['title'],
            'description' => $description,
            'image' => $image,
            'author_name' => $story['author_name'] ?? '',
            'author_url' => !empty($story['author_name']) 
                ? route('user.profile', ['username' => $story['author_name']]) 
                : null,
        ];
    }

    /**
     * Получить ID замьюченных пользователей
     */
    private function getMutedUserIds(): array
    {
        if (!Auth::check() || $this->muteService === null) {
            return [];
        }
        return $this->muteService->getMutedUserIds(Auth::id());
    }
}