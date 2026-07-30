<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use W3a\Core\Foundation\Container;
use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Models\ReadRibbon;
use App\Modules\Stories\Services\RankingService;
use App\Modules\Origins\Models\Domain;
use App\Modules\Tags\Models\TagFilter;
use App\Modules\Auth\Services\Auth;
use App\Modules\Muted\Services\MuteService;

/**
 * Сервис для фильтрации и получения списков историй.
 */
class StoryFilterService
{
    private Story $storyModel;
    private Domain $domainModel;
    private Container $container;
    private MuteService $muteService;
    private RankingService $rankingService;

    /**
     * Конструктор с инъекцией зависимостей.
     * 
     * @param Story $storyModel Модель историй
     * @param Domain $domainModel Модель доменов
     * @param Container $container DI-контейнер
     */
    public function __construct(Story $storyModel, Domain $domainModel, Container $container, ?MuteService $muteService = null, ?RankingService $rankingService = null)
    {
        $this->storyModel = $storyModel;
        $this->domainModel = $domainModel;
        $this->container = $container;
        $this->muteService = $muteService;

        $this->rankingService = $rankingService ?? new RankingService();
    }

    /**
     * Получает ленту историй с учётом всех фильтров.
     *
     * @param int $perPage Количество на страницу
     * @param int $offset Смещение
     * @param string $tagslug Фильтр по тегу
     * @param string $domain Фильтр по домену
     * @return array Массив историй
     */
    public function getFilteredStories(
        int $perPage,
        int $offset,
        string $tagslug = '',
        string $domain = '',
        string $sort = 'hot',
        string $author = ''
    ): array {
        $showDeleted = Auth::isAdmin();
        $excludeTagIds = $this->getUserExcludedTags();
        $mutedUserIds = $this->getMutedUserIds();

        return $this->storyModel->getFeed($perPage, $offset, $tagslug, $showDeleted, $domain, $excludeTagIds, $sort, $author, $mutedUserIds);
    }

    /**
     * Получает общее количество историй с учётом фильтров.
     */
    public function getTotalCount(
        string $tagname = '',
        string $domain = '',
        string $author = ''
    ): int {
        $excludeTagIds = $this->getUserExcludedTags();
        $mutedUserIds = $this->getMutedUserIds();
        return $this->storyModel->getTotalCount($tagname, $domain, $excludeTagIds, $author, $mutedUserIds);
    }

    /**
     * Получает комментарии для истории в виде дерева с сортировкой по Вильсону
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
     * Получает список забаненных доменов (в нижнем регистре).
     *
     * @return array Массив забаненных доменов
     */
    public function getBannedDomains(): array
    {
        $bannedDomains = $this->domainModel->getBannedDomains();
        $domains = array_column($bannedDomains, 'domain');
        return array_map('strtolower', $domains);
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

        // Получаем модель из контейнера вместо new TagFilter()
        $filterModel = $this->container->get(TagFilter::class);
        return $filterModel->getFilteredTagIds(Auth::id());
    }

    /**
     * Получает количество новых комментариев для списка историй.
     *
     * @param array $storyIds Массив ID историй
     * @return array Массив [story_id => count]
     */
    public function getNewCommentsCounts(array $storyIds): array
    {
        if (!Auth::check() || empty($storyIds)) {
            return [];
        }

        $readRibbon = $this->container->get(ReadRibbon::class);

        // Получаем замьюченных
        $mutedUserIds = $this->getMutedUserIds();

        return $readRibbon->getNewCommentsCounts(
            Auth::id(),
            array_map('intval', $storyIds),
            $mutedUserIds // Передаём в модель
        );
    }



    /**
     * Получает одну историю с информацией об авторе.
     */
    public function getStoryWithAuthor(int $storyId): ?array
    {
        $showDeleted = Auth::isAdmin();
        return $this->storyModel->getSingleWithAuthor($storyId, $showDeleted);
    }

    /**
     * Подготовить данные для Open Graph мета-тегов.
     * 
     * @param array $story Данные истории (уже загруженные)
     * @return array Массив с ключами: title, description, image, author_name, author_url
     */
    public function getStoryOpenGraphData(array $story): array
    {
        if (empty($story)) {
            return [];
        }

        // Описание: либо из текста, либо кол-во комментариев
        $description = '';
        if (!empty($story['description'])) {
            $description = mb_substr(strip_tags($story['description']), 0, 200);
            if (mb_strlen($story['description']) > 200) {
                $description .= '...';
            }
        } else {
            $description = (int)$story['comments_count'] . ' комментариев';
        }

        // Изображение: превью ссылки или дефолтное
        $image = null;
        if (!empty($story['url'])) {
            $image = rtrim(config('config.app.url'), '/') . '/' . $story['id'] . '.png';
        }

        return [
            'title' => $story['title'],
            'description' => $description,
            'image' => $image,
            'author_name' => $story['author_name'],
            'author_url' => route('user.profile', ['username' => $story['author_name']]),
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
