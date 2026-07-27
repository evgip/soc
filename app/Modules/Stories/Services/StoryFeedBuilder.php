<?php

namespace App\Modules\Stories\Services;

use App\Modules\Stories\DTO\StoryFeedDTO;
use App\Modules\Votes\Models\Vote;
use W3a\Core\Container;
use W3a\Core\Request;

class StoryFeedBuilder
{
    private Container $container;
    private Request $request;
    private StoryFilterService $filterService;

    public function __construct(Container $container, Request $request)
    {
        $this->container = $container;
        $this->request = $request;
        $this->filterService = $container->get(StoryFilterService::class);
    }

    /**
     * Собирает данные для ленты историй (главной, по тегу, домену или автору).
     */
    public function build(
        string $tagslug = '',
        string $domain = '',
        string $author = '',
        array $userContext = [],
        bool $canUserDownvote = false,
        array $pageData = []
    ): StoryFeedDTO {
        $currentPage = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config('constants.pagination.stories_per_page', 15, 'int');
        $offset = ($currentPage - 1) * $perPage;

        $sort = $this->request->getParams('sort', 'hot');
        if (!in_array($sort, ['hot', 'new', 'top'], true)) {
            $sort = 'hot';
        }

        // В оригинальном userStories сортировка жестко задана как 'hot'
        $actualSort = $author !== '' ? 'hot' : $sort;

        $stories = $this->filterService->getFilteredStories($perPage, $offset, $tagslug, $domain, $actualSort, $author);
        $totalStories = $this->filterService->getTotalCount($tagslug, $domain, $author);
        $totalPages = (int)ceil($totalStories / $perPage);

        $bannedDomainsCache = $this->filterService->getBannedDomains();
        $storyIds = array_column($stories, 'id');
        $newCommentsMap = $this->filterService->getNewCommentsCounts($storyIds);

        $currentVotes = [];
        if (!empty($userContext['isLoggedIn'])) {
            $voteModel = $this->container->get(Vote::class);
            $currentVotes = $voteModel->getUserVotesForStories($userContext['id'], $storyIds);
        }

        $rssFeed = $this->buildRssFeed($tagslug, $author, $pageData);

        return new StoryFeedDTO(
            stories: $stories,
            currentPage: $currentPage,
            totalPages: $totalPages,
            newCommentsMap: $newCommentsMap,
            bannedDomainsCache: $bannedDomainsCache,
            sort: $sort,
            domain: $domain,
            author: $author !== '' ? $author : null,
            currentUserId: $userContext['id'] ?? 0,
            isAdmin: $userContext['isAdmin'] ?? false,
            canUserDownvote: $canUserDownvote,
            currentVotes: $currentVotes,
            rssFeed: $rssFeed,
            pageTitle: $pageData['title'] ?? 'Лента историй',
            extraData: $pageData
        );
    }

    private function buildRssFeed(string $tagslug, string $author, array $pageData): array
    {
        if ($author !== '') {
            return [
                'title' => 'Публикации ' . e($author),
                'url' => '/u/' . e($author) . '/rss',
            ];
        }

        if ($tagslug !== '') {
            return [
                'title' => 'Тег #' . e($pageData['tagInfo']['name'] ?? $tagslug),
                'url' => '/t/' . e($tagslug) . '/rss',
            ];
        }

        return [
            'title' => 'Новые истории',
            'url' => '/rss',
        ];
    }
}
