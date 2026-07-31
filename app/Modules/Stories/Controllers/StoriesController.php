<?php

declare(strict_types=1);

namespace App\Modules\Stories\Controllers;

use App\BaseController;
use W3a\Core\Http\Session;
use W3a\Core\Http\Response;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Http\ViewResponse;

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
use W3a\Core\Http\JsonResponse;

class StoriesController extends BaseController
{
    // =========================================================================
    // ЛЕНТА ИСТОРИЙ
    // =========================================================================
    public function index(string $tagslug = '', string $domain = ''): ViewResponse
    {
        $userContext = $this->getUserContext();

        // Получаем специфичные для страницы данные (wiki, tagInfo) и устанавливаем OG-теги
        $pageData = $this->buildIndexPageData($tagslug, $domain);

        // Делегируем сборку ленты сервису
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
            $this->session()->flash('error', $e->getMessage());

            return $this->redirectBack();
        } catch (\Throwable $e) {
            $this->logError($e, 'Stories.create');
            $this->session()->flash('error', 'Произошла ошибка при создании публикации.');

            return $this->redirectBack();
        }

        $this->session()->flash('success', 'Ваша история успешно опубликована!');

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
            $this->container->get(Session::class)->flash('error', 'У вас нет прав для изменения этой публикации.');
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

        if (!$story || !$this->service(StoryService::class)->canEditStory($story)) {
            $this->session()->flash('error', 'У вас нет прав для изменения этой публикации.');
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
            $this->session()->flash('error', $e->getMessage());
            return $this->redirectBack();
        } catch (\Throwable $e) {
            $this->logError($e, 'Stories.update');
            $this->session()->flash('error', 'Произошла ошибка при редактировании.');
            return $this->redirectBack();
        }

        $this->session()->flash('success', 'Публикация успешно отредактирована.');

        return $this->redirect('/story/' . $storyId);
    }

    // =========================================================================
    // АДМИНИСТРИРОВАНИЕ ИСТОРИЙ
    // =========================================================================

    public function adminDelete(string $id): RedirectResponse
    {
        $userContext = $this->getUserContext();
        $this->service(StoryService::class)->deleteStory((int)$id, $userContext['id']);

        return $this->redirectBack();
    }

    public function adminRestore(string $id): RedirectResponse
    {
        $userContext = $this->getUserContext();
        $this->service(StoryService::class)->restoreStory((int)$id, $userContext['id']);

        return $this->redirectBack();
    }

    // =========================================================================
    // ПОДПИСКА И ПРОЧТЕНИЕ
    // =========================================================================
    public function toggleFollow(string $id): Response
    {
        $storyId = (int)$id;
        $userContext = $this->getUserContext();

        // Используем репозиторий для операций с данными
        $storyRepo = $this->container->get(StoryRepository::class);

        // Выполняем переключение (внутри репозитория уже есть защита по user_id)
        $storyRepo->toggleFollow($storyId, $userContext['id']);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            // Получаем актуальный статус через тот же репозиторий
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
        $validator = $this->container->get(\W3a\Core\Support\Validator::class); // Уточните неймспейс, если отличается (в оригинале AppCoreValidator::class)
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

        // Делегируем сборку ленты сервису (автоматически применит sort='hot')
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
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Получить экземпляр Session из DI-контейнера.
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }
}
