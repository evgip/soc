<?php

declare(strict_types=1);

namespace App\Modules\Stories\Controllers;

use App\BaseController;
use W3a\Core\Http\Response;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Http\JsonResponse;
use W3a\Core\Support\MessageBag;

use App\Modules\Stories\Services\StoryService;
use App\Modules\Stories\Services\ReadRibbonService;
use App\Modules\Stories\Services\UrlFetcherService;
use App\Modules\Stories\Services\StoryPageService;
use App\Modules\Stories\Services\StoryFeedBuilder;
use App\Modules\Stories\Repositories\StoryRepository;
use App\Modules\Stories\Models\Story;
use App\Modules\Tags\Services\TagFilterService;
use App\Modules\Tags\Models\Tag;
use App\Modules\Users\Models\User;
use App\Modules\Content\Core\Markdown;
use App\Modules\Wiki\Services\WikiService;

class StoriesController extends BaseController
{
    // =========================================================================
    // ЛЕНТА ИСТОРИЙ
    // =========================================================================
    public function index(string $tagslug = '', string $domain = ''): ViewResponse
    {
        $userContext = $this->getUserContext();

        $pageData = $this->buildIndexPageData($tagslug, $domain);

        $feed = $this->service(StoryFeedBuilder::class)->build(
            tagslug: $tagslug,
            domain: $domain,
            author: '',
            userContext: $userContext,
            canUserDownvote: $this->canUserDownvote($userContext['id']),
            pageData: $pageData
        );

        return $this->render('index', [
            'stories' => $feed->stories,
            'currentPage' => $feed->currentPage,
            'totalPages' => $feed->totalPages,
            'newCommentsMap' => $feed->newCommentsMap,
            'bannedDomainsCache' => $feed->bannedDomainsCache,
            'sort' => $feed->sort,
            'domain' => $feed->domain,
            'currentUserId' => $feed->currentUserId,
            'isAdmin' => $feed->isAdmin,
            'canUserDownvote' => $feed->canUserDownvote,
            'currentVotes' => $feed->currentVotes,
            'rssFeed' => $feed->rssFeed,
            'title' => $feed->pageTitle,
            'tagInfo' => $feed->extraData['tagInfo'] ?? '',
            'wikiPages' => $feed->extraData['wikiPages'] ?? false,
            'primaryWikiPage' => $feed->extraData['primaryWikiPage'] ?? false,
            'wikiPagesCount' => $feed->extraData['wikiPagesCount'] ?? false,
        ]);
    }

    // =========================================================================
    // ПРОСМОТР ОДНОЙ ИСТОРИИ
    // =========================================================================
    public function show(string $id): ViewResponse
    {
        $userContext = $this->getUserContext();
        $viewModel = $this->service(StoryPageService::class)->buildShowPageData((int)$id, $userContext);

        $this->setOpenGraph([
            'type' => 'article',
            'title' => $viewModel->story['title'],
            'description' => $viewModel->story['seo_description'] ?? '',
            'image' => $viewModel->story['og_image'] ?? '',
        ]);

        return $this->render('show', [
            'title' => $viewModel->story['title'],
            'viewModel' => $viewModel,
        ]);
    }

    // =========================================================================
    // СОЗДАНИЕ ИСТОРИИ
    // =========================================================================

    public function showCreateForm(): ViewResponse
    {
        $tagModel = $this->container->get(Tag::class);
        $availableTags = $tagModel->getAllTags(false);

        return $this->render('create', [
            'title' => 'Поделиться интересным',
            'availableTags' => $availableTags,
            'request' => $this->request
        ]);
    }

    /**
     * Обработка создания новой истории.
     */
    public function create(): RedirectResponse
    {
        $data = [
            'title' => $this->request->getParams('title'),
            'url' => $this->request->getParams('url') ?: null,
            'description' => $this->request->getParams('description') ?: null,
            'tags' => $this->request->getParams('tags') ?? [],
            'user_is_following' => is_numeric($this->request->getParams('user_is_following')) ? 1 : 0,
        ];

        $userContext = $this->getUserContext();

        try {
            $storyId = $this->service(StoryService::class)->createStory($data, $userContext['id']);
        } catch (\App\Modules\Stories\Exceptions\StoryValidationException | \App\Modules\Stories\Exceptions\BannedDomainException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack();
        } catch (\Throwable $e) {
            $this->logError($e, 'Stories.create');
            MessageBag::flashMessage('error', 'Произошла ошибка при создании публикации.');
            return $this->redirectBack();
        }

        MessageBag::flashMessage('success', 'Ваша история успешно опубликована!');
        return $this->redirect('/story/' . $storyId);
    }

    // =========================================================================
    // РЕДАКТИРОВАНИЕ ИСТОРИИ
    // =========================================================================

    public function showEditForm(string $id): Response
    {
        $storyId = (int)$id;

        $storyModel = $this->container->get(Story::class);
        $story = $storyModel->find($storyId);

        $userContext = $this->getUserContext();

        if (!$story || !$this->service(StoryService::class)->canEditStory($story, $userContext['id'])) {
            MessageBag::flashMessage('error', 'У вас нет прав для изменения этой публикации.');
            return $this->redirectBack('/');
        }

        $tagModel = $this->container->get(Tag::class);

        return $this->render('edit', [
            'title' => 'Редактирование публикации',
            'story' => $story,
            'availableTags' => $tagModel->getAllTags(),
            'activeTagIds' => $storyModel->getStoryTagIds($storyId),
            'request' => $this->request
        ]);
    }

    /**
     * Обработка обновления существующей истории.
     */
    public function update(string $id): RedirectResponse
    {
        $storyId = (int)$id;
        $storyModel = $this->container->get(Story::class);
        $story = $storyModel->find($storyId);
        $userContext = $this->getUserContext();

        if (!$story || !$this->service(StoryService::class)->canEditStory($story, $userContext['id'])) {
            MessageBag::flashMessage('error', 'У вас нет прав для изменения этой публикации.');
            return $this->redirectBack();
        }

        $data = [
            'title' => $this->request->getParams('title'),
            'url' => $this->request->getParams('url') ?: null,
            'description' => $this->request->getParams('description') ?: null,
            'tags' => $this->request->post('tags', []),
            'user_is_following' => $this->request->post('user_is_following') !== null ? 1 : 0,
        ];

        try {
            $this->service(StoryService::class)->updateStory($storyId, $data);
        } catch (\App\Modules\Stories\Exceptions\StoryValidationException | \App\Modules\Stories\Exceptions\BannedDomainException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack();
        } catch (\Throwable $e) {
            $this->logError($e, 'Stories.update');
            MessageBag::flashMessage('error', 'Произошла ошибка при редактировании.');
            return $this->redirectBack();
        }

        MessageBag::flashMessage('success', 'Публикация успешно отредактирована.');
        return $this->redirect('/story/' . $storyId);
    }

    // =========================================================================
    // АДМИНИСТРИРОВАНИЕ ИСТОРИЙ
    // =========================================================================

    public function adminDelete(string $id): RedirectResponse
    {
        $userContext = $this->getUserContext();
        $this->service(StoryService::class)->deleteStory((int)$id, $userContext['id']);

        MessageBag::flashMessage('success', 'История успешно удалена.');
        return $this->redirectBack();
    }

    public function adminRestore(string $id): RedirectResponse
    {
        $userContext = $this->getUserContext();
        $this->service(StoryService::class)->restoreStory((int)$id, $userContext['id']);

        MessageBag::flashMessage('success', 'История успешно восстановлена.');
        return $this->redirectBack();
    }

    // =========================================================================
    // ПОДПИСКА И ПРОЧТЕНИЕ
    // =========================================================================
    public function toggleFollow(string $id): Response
    {
        $storyId = (int)$id;
        $userContext = $this->getUserContext();

        $storyRepo = $this->container->get(StoryRepository::class);
        $storyRepo->toggleFollow($storyId, $userContext['id']);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $isFollowing = $storyRepo->isFollowing($storyId, $userContext['id']);

            return $this->json([
                'success' => true,
                'is_following' => $isFollowing,
            ]);
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '/story/' . $storyId;
        return $this->redirectBack($referer);
    }

    public function markRead(string $id): RedirectResponse
    {
        $storyId = (int)$id;
        $this->service(ReadRibbonService::class)->markAsRead($storyId);

        $referer = $_SERVER['HTTP_REFERER'] ?? '/story/' . $storyId;
        return $this->redirectBack($referer);
    }

    // =========================================================================
    // AJAX ENDPOINTS
    // =========================================================================

    public function fetchUrlTitle(): JsonResponse
    {
        $url = $this->request->getParams('url');

        if (empty($url)) {
            return $this->json(['title' => '', 'url' => '']);
        }

        $fetcher = $this->container->get(UrlFetcherService::class);
        $attributes = $fetcher->fetchAttributes($url);

        return $this->json($attributes);
    }

    public function preview(): JsonResponse
    {
        if (!$this->request->isCsrfValid()) {
            return $this->json(['error' => 'Неверный CSRF токен'], 419);
        }

        $text = $this->request->post('text', '');
        $allowImages = (bool)$this->request->post('allow_images', true);

        $markdown = $this->container->get(Markdown::class);
        $html = $markdown->parse($text, $allowImages);

        return $this->json([
            'html' => $html,
            'success' => true
        ]);
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================
    private function buildIndexPageData(string $tagslug, string $domain): array
    {
        $data = [
            'title' => 'Лента историй',
            'tagInfo' => '',
            'wikiPages' => false,
            'primaryWikiPage' => false,
            'wikiPagesCount' => false,
        ];

        if ($tagslug) {
            $data['title'] = "Публикации с тегом # " . e($tagslug);

            $tagFilterService = $this->service(TagFilterService::class);
            $ogData = $tagFilterService->getTagOpenGraphData($tagslug);
            $this->setOpenGraph([
                'type' => 'article',
                'title' => $ogData['title'],
                'description' => $ogData['description'],
                'image' => config('config.app.url') . '/',
            ]);

            $data['tagInfo'] = $tagFilterService->getByInfoSlug($tagslug);

            if (!empty($data['tagInfo']['id'])) {
                $wikiService = $this->service(WikiService::class);
                $wikiPages = $wikiService->getPagesForTag($data['tagInfo']['id']);
                $data['wikiPages'] = $wikiPages;
                $data['primaryWikiPage'] = $wikiService->getPrimaryPageForTag($data['tagInfo']['id']);
                $data['wikiPagesCount'] = count($wikiPages);
            }
        } elseif ($domain) {
            $data['title'] = "Публикации с домена " . e($domain);
            $this->setOpenGraph([
                'type' => 'article',
                'title' => $data['title'],
                'description' => null,
                'image' => config('config.app.url') . '/',
            ]);
        }

        return $data;
    }

    private function validateAuthor(string $username): string
    {
        $username = trim($username);

        if ($username === '') {
            return '';
        }

        $validator = $this->container->get(\W3a\Core\Support\Validator::class);
        $validator->validate(
            ['username' => $username],
            ['username' => 'required|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/']
        );

        if (!$validator->isValid()) {
            return '';
        }

        $userModel = $this->container->get(User::class);
        $user = $userModel->findByName($username);

        return $user ? $username : '';
    }

    // =========================================================================
    // ЛЕНТА ПОЛЬЗОВАТЕЛЯ
    // =========================================================================
    public function userStories(string $username): ViewResponse
    {
        $validator = $this->container->get(\W3a\Core\Support\Validator::class);
        $validator->validate(
            ['username' => $username],
            ['username' => 'required|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/']
        );

        if (!$validator->isValid()) {
            throw new \W3a\Core\Exceptions\NotFoundException("Пользователь не найден");
        }

        $userModel = $this->container->get(\App\Modules\Users\Models\User::class);
        $user = $userModel->findByName($username);

        if (!$user) {
            throw new \W3a\Core\Exceptions\NotFoundException("Пользователь не найден");
        }

        $userContext = $this->getUserContext();
        $pageTitle = 'Публикации пользователя ' . e($username);

        $this->setOpenGraph([
            'type' => 'article',
            'title' => $pageTitle,
            'description' => null,
            'image' => config('config.app.url') . '/',
        ]);

        $feed = $this->service(\App\Modules\Stories\Services\StoryFeedBuilder::class)->build(
            tagslug: '',
            domain: '',
            author: $username,
            userContext: $userContext,
            canUserDownvote: $this->canUserDownvote($userContext['id']),
            pageData: ['title' => $pageTitle]
        );

        return $this->render('index', [
            'stories' => $feed->stories,
            'currentPage' => $feed->currentPage,
            'totalPages' => $feed->totalPages,
            'newCommentsMap' => $feed->newCommentsMap,
            'bannedDomainsCache' => $feed->bannedDomainsCache,
            'sort' => $feed->sort,
            'domain' => $feed->domain,
            'author' => $feed->author,
            'currentUserId' => $feed->currentUserId,
            'isAdmin' => $feed->isAdmin,
            'canUserDownvote' => $feed->canUserDownvote,
            'currentVotes' => $feed->currentVotes,
            'rssFeed' => $feed->rssFeed,
            'title' => $feed->pageTitle,
        ]);
    }

    // =========================================================================
    // ЛЕНТА ПОДПИСОК
    // =========================================================================
    public function subscribed(): ViewResponse
    {
        $userContext = $this->getUserContext();

        if (!$userContext['isLoggedIn']) {
            return $this->redirect('/');
        }

        $subscriptionService = $this->service(\App\Modules\Subscriptions\Services\SubscriptionService::class);
        $followedUserIds = $subscriptionService->getFollowedUserIds($userContext['id']);
        $followedTagIds = $subscriptionService->getFollowedTagIds($userContext['id']);

        // 1. Проверяем, есть ли вообще подписки
        $isEmptyState = empty($followedUserIds) && empty($followedTagIds);

        if ($isEmptyState) {
            // Если подписок нет, сразу готовим пустые данные, не нагружая БД
            $stories = [];
            $currentPage = 1;
            $totalPages = 0;
            $newCommentsMap = [];
        } else {
            // 2. Если подписки есть, делаем запросы как раньше
            $page = max(1, (int)$this->request->getParams('page', 1));
            $limit = 20;
            $offset = ($page - 1) * $limit;
            $sort = $this->request->getParams('sort', 'new');

            $storyModel = $this->container->get(Story::class);
            $stories = $storyModel->getSubscribedFeed(
                $userContext['id'], $followedUserIds, $followedTagIds, $limit, $offset, $sort
            );
            
            $totalCount = $storyModel->getSubscribedTotalCount(
                $userContext['id'], $followedUserIds, $followedTagIds
            );
            $totalPages = (int)ceil($totalCount / $limit);

            $storyIds = array_column($stories, 'id');
            $muteService = $this->service(\App\Modules\Muted\Services\MuteService::class);
            $mutedUserIds = $muteService->getMutedUserIds($userContext['id']);
            
            $readRibbon = $this->container->get(\App\Modules\Stories\Models\ReadRibbon::class);
            $newCommentsMap = $readRibbon->getNewCommentsCounts($userContext['id'], $storyIds, $mutedUserIds);
        }

        // 3. Передаем флаг isEmptyState в шаблон
        return $this->render('index', [
            'stories' => $stories,
            'currentPage' => $currentPage ?? 1,
            'totalPages' => $totalPages ?? 0,
            'newCommentsMap' => $newCommentsMap,
            'bannedDomainsCache' => [],
            'sort' => $sort ?? 'new',
            'domain' => '',
            'author' => '',
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'canUserDownvote' => $this->canUserDownvote($userContext['id']),
            'currentVotes' => [],
            'rssFeed' => '',
            'title' => 'Мои подписки',
            'tagInfo' => [],
            'wikiPages' => false,
            'primaryWikiPage' => false,
            'wikiPagesCount' => false,
            'isEmptyState' => $isEmptyState, // <-- НОВАЯ ПЕРЕМЕННАЯ
        ]);
    }
}