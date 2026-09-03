<?php

declare(strict_types=1);

namespace App\Modules\Stories\Controllers;

use App\BaseController;
use W3a\Core\Http\Response;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Http\JsonResponse;
use W3a\Core\Support\MessageBag;

use W3a\Core\Storage\StorageManager;
use W3a\Core\Storage\UploadedFile;
use W3a\Core\Storage\FileValidator;
use W3a\Core\Storage\Exceptions\ValidationException;

use App\Modules\Stories\Services\StoryService;
use App\Modules\Stories\Services\ReadRibbonService;
use App\Modules\Stories\Services\StoryPageService;
use App\Modules\Stories\Services\StoryFeedBuilder;
use App\Modules\Stories\Repositories\StoryRepository;
use App\Modules\Stories\Models\Story;
use App\Modules\Tags\Services\TagFilterService;
use App\Modules\Tags\Models\Tag;
use App\Modules\Users\Models\User;
use App\Modules\Wiki\Services\WikiService;
use App\Modules\Stories\Exceptions\StoryValidationException;

use App\Modules\Common\Support\Layout; 

use App\Modules\Stories\Requests\CreateStoryRequest;
use App\Modules\Stories\Requests\UpdateStoryRequest;

class StoriesController extends BaseController
{
	// =========================================================================
	// ЛЕНТА СТАТЕЙ (4 секции)
	// =========================================================================
	public function index(string $tagslug = ''): ViewResponse
	{
		$userContext = $this->getUserContext();
		
		// Если это фильтр по тегу или автору — используем старую логику
		if ($tagslug !== '') {
			return $this->renderTagFilter($tagslug, $userContext);
		}

		$pageData = $this->buildIndexPageData($tagslug);

		// 1. Собираем Medium-секции
		$forYou = [];
		$trending = [];
		$staffPicks = [];
		$hasPersonalization = false;

		if ($userContext['isLoggedIn']) {
			$recommendationService = $this->service(\App\Modules\Stories\Services\RecommendationService::class);
			$forYou = $recommendationService->getForYouFeed($userContext['id'], 6);
			
			// Есть ли персонализация?
			$subscriptionService = $this->service(\App\Modules\Subscriptions\Services\SubscriptionService::class);
			$followedUsers = $subscriptionService->getFollowedUserIds($userContext['id']);
			$followedTags = $subscriptionService->getFollowedTagIds($userContext['id']);
			$hasPersonalization = !empty($followedUsers) || !empty($followedTags);
		}

		$trendingService = $this->service(\App\Modules\Stories\Services\TrendingService::class);
		$trending = $trendingService->getTrending(5);

		$staffPicksService = $this->service(\App\Modules\Stories\Services\StaffPicksService::class);
		$staffPicks = $staffPicksService->getStaffPicks(3);

		// 2. Основная лента (Hot/New/Top) — существующая логика
		$feed = $this->service(StoryFeedBuilder::class)->build(
			tagslug: $tagslug,
			author: '',
			userContext: $userContext,
			pageData: $pageData
		);

		// Исключаем дубли: убираем из основной ленты то, что уже показано в рекомендациях
		if (!empty($forYou)) {
			$forYouIds = array_column($forYou, 'id');
			$feed->stories = array_values(array_filter($feed->stories, fn($s) => !in_array($s['id'], $forYouIds)));
		}

		// 3. Формируем ViewModel
		$tagModel = $this->container->get(Tag::class);
		$userModel = $this->container->get(User::class);
		$topAuthors = $userModel->getTopAuthors(
			limit: 5,
			excludeUserId: $userContext['isLoggedIn'] ? $userContext['id'] : null
		);

		// Добавляем флаг подписки для каждого автора
		if ($userContext['isLoggedIn'] && !empty($topAuthors)) {
			$subscriptionService = $this->service(\App\Modules\Subscriptions\Services\SubscriptionService::class);
			foreach ($topAuthors as &$author) {
				$author['is_following'] = $subscriptionService->isFollowingUser(
					$userContext['id'], (int)$author['id']
				);
			}
			unset($author);
		}

		$viewModel = new \App\Modules\Stories\ViewModels\HomeFeedViewModel(
			stories: $feed->stories,
			currentPage: $feed->currentPage,
			totalPages: $feed->totalPages,
			newCommentsMap: $feed->newCommentsMap,
			sort: $feed->sort,
			currentUserId: $feed->currentUserId,
			isAdmin: $feed->isAdmin,
			currentVotes: $feed->currentVotes,
			forYou: $forYou,
			trending: $trending,
			staffPicks: $staffPicks,
			isLoggedIn: $userContext['isLoggedIn'],
			hasPersonalization: $hasPersonalization,
			pageTitle: $feed->pageTitle,
			rssFeed: $feed->rssFeed,
			allTags: $tagModel->getAllTags(),
			topAuthors: $topAuthors,
		);

// 🔑 Устанавливаем широкий макет для главной
		Layout::set(Layout::WIDE);

		$this->setOpenGraph([
			'type' => 'website',
			'title' => $feed->pageTitle,
			'description' => 'Читайте лучшие публикации, подписывайтесь на авторов и участвуйте в обсуждениях.',
			'image' => config('app.url') . '/favicon-512.png',
		]);

		return $this->render('index', [
			'viewModel' => $viewModel,
			'title' => $feed->pageTitle,
			'rssFeed' => $feed->rssFeed,
		]);
	}

	/**
	 * Отдельный метод для фильтров по тегу (использует старую логику)
	 */
	private function renderTagFilter(string $tagslug, array $userContext): ViewResponse
	{
		$pageData = $this->buildIndexPageData($tagslug);

		$feed = $this->service(StoryFeedBuilder::class)->build(
			tagslug: $tagslug,
			author: '',
			userContext: $userContext,
			pageData: $pageData
		);

		return $this->render('index', [
			'stories' => $feed->stories,
			'currentPage' => $feed->currentPage,
			'totalPages' => $feed->totalPages,
			'newCommentsMap' => $feed->newCommentsMap,
			'sort' => $feed->sort,
			'currentUserId' => $feed->currentUserId,
			'isAdmin' => $feed->isAdmin,
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
	// ПРОСМОТР ОДНОЙ СТАТЬИ
	// =========================================================================
	public function show(string $id): ViewResponse
	{
		$storyId = (int)$id;
		
		// Проверяем что статья опубликована (черновики недоступны никому, даже автору)
		$storyModel = $this->container->get(Story::class);
		$story = $storyModel->find($storyId);
		
		if (!$story || ($story['status'] ?? 'published') !== 'published' || !empty($story['deleted_at'])) {
			throw new \W3a\Core\Exceptions\NotFoundException("Статья не найдена");
		}

		$userContext = $this->getUserContext();
		$friendLinkToken = $this->request->query('fl', null);
		$viewModel = $this->service(StoryPageService::class)->buildShowPageData($storyId, $userContext, $friendLinkToken);

		$ogImage = get_story_first_image($viewModel->story, 'large');

		$this->setOpenGraph([
			'type' => 'article',
			'title' => $viewModel->story['title'],
			'description' => $viewModel->story['description_text'] ?? '',
			'image' => $ogImage ?: config('app.url') . '/default-og.jpg',
		]);

		$isFollowing = false;
		if ($userContext['isLoggedIn'] && (int)$viewModel->story['user_id'] !== $userContext['id']) {
			$subscriptionService = $this->service(\App\Modules\Subscriptions\Services\SubscriptionService::class);
			$isFollowing = $subscriptionService->isFollowingUser($userContext['id'], (int)$viewModel->story['user_id']);
		}

		return $this->render('show', [
			'title' => $viewModel->story['title'],
			'viewModel' => $viewModel,
			'isFollowing' => $isFollowing,
		]);
	}

    // =========================================================================
    // СОЗДАНИЕ СТАТЬИ
    // =========================================================================

    public function showCreateForm(): ViewResponse
    {
        $tagModel = $this->container->get(Tag::class);
        $availableTags = $tagModel->getAllTags(false);

        // 🔑 Устанавливаем широкий макет для главной
        Layout::set(Layout::WIDE);

        return $this->render('create', [
            'title' => 'Написать статью',
            'availableTags' => $availableTags,
            'request' => $this->request
        ]);
    }

	/**
	 * Обработка создания новой статьи.
	 * Заголовок извлекается из JSON автоматически в StoryService.
	 * Параметр action: 'publish' (опубликовать) или 'draft' (сохранить черновик)
	 */
	public function create(): RedirectResponse
	{
		$userContext = $this->getUserContext();

		$validated = $this->validateForm(
			new CreateStoryRequest($this->request, $this->container)
		);
		
		if ($validated instanceof Response) {
			return $this->redirectBack();
		}

		// Rate limiting (после валидации, до создания)
		try {
			$this->container->get(\W3a\Core\Security\RateLimiter::class)->check('story.create');
		} catch (\W3a\Core\Exceptions\RateLimitExceededException $e) {
			MessageBag::flashMessage('error', 'Превышен лимит: максимум 5 статей в сутки.');
			return $this->redirectBack();
		}

		$status = ($validated['action'] ?? '') === 'draft' ? 'draft' : 'published';

		try {
			$storyId = $this->service(StoryService::class)->createStory($validated, $userContext['id'], $status);
		} catch (StoryValidationException $e) {
			MessageBag::flashMessage('error', $e->getMessage());
			return $this->redirectBack();
		} catch (\Throwable $e) {
			$this->logError($e, 'Stories.create');
			MessageBag::flashMessage('error', 'Произошла ошибка при создании публикации.');
			return $this->redirectBack();
		}

		if ($status === 'draft') {
			MessageBag::flashMessage('success', 'Черновик сохранён.');
			return $this->redirect('/stories/' . $storyId . '/edit');
		}

		MessageBag::flashMessage('success', 'Ваша статья успешно опубликована!');
		return $this->redirect('/story/' . $storyId);
	}

    // =========================================================================
    // РЕДАКТИРОВАНИЕ СТАТЬИ
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

        // 🔑 Устанавливаем широкий макет для главной
        Layout::set(Layout::WIDE);

        return $this->render('edit', [
            'title' => 'Редактирование публикации',
            'story' => $story,
            'availableTags' => $tagModel->getAllTags(),
            'activeTagIds' => $storyModel->getStoryTagIds($storyId),
            'request' => $this->request
        ]);
    }

	/**
	 * Обработка обновления существующей статьи.
	 * Параметр action: 'draft' (сохранить черновик), 'publish' (опубликовать), 
	 * или пусто (просто сохранить изменения для уже опубликованной)
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

		$validated = $this->validateForm(
			new UpdateStoryRequest($this->request, $this->container)
		);
		
		if ($validated instanceof Response) {
			return $this->redirectBack();
		}

		$action = $validated['action'] ?? '';
		$currentStatus = $story['status'] ?? 'published';
		
		if ($currentStatus === 'draft' && $action === 'publish') {
			$newStatus = 'published';
		} elseif ($currentStatus === 'draft' && $action === 'draft') {
			$newStatus = 'draft';
		} else {
			$newStatus = 'published';
		}

		try {
			$this->service(StoryService::class)->updateStory($storyId, $validated, $newStatus);
		} catch (StoryValidationException $e) {
			MessageBag::flashMessage('error', $e->getMessage());
			return $this->redirectBack();
		} catch (\Throwable $e) {
			$this->logError($e, 'Stories.update');
			MessageBag::flashMessage('error', 'Произошла ошибка при редактировании.');
			return $this->redirectBack();
		}

		if ($newStatus === 'draft') {
			MessageBag::flashMessage('success', 'Черновик сохранён.');
			return $this->redirect('/stories/' . $storyId . '/edit');
		}

		if ($currentStatus === 'draft' && $newStatus === 'published') {
			MessageBag::flashMessage('success', 'Ваша статья успешно опубликована!');
		} else {
			MessageBag::flashMessage('success', 'Публикация успешно отредактирована.');
		}
		
		return $this->redirect('/story/' . $storyId);
	}

    // =========================================================================
    // АДМИНИСТРИРОВАНИЕ СТАТЕЙ
    // =========================================================================

    public function adminDelete(string $id): RedirectResponse
    {
        $userContext = $this->getUserContext();
        $this->service(StoryService::class)->deleteStory((int)$id, $userContext['id']);

        MessageBag::flashMessage('success', 'Статья успешно удалена.');
        return $this->redirectBack();
    }

    public function adminRestore(string $id): RedirectResponse
    {
        $userContext = $this->getUserContext();
        $this->service(StoryService::class)->restoreStory((int)$id, $userContext['id']);

        MessageBag::flashMessage('success', 'Статья успешно восстановлена.');
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
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================
    
    private function buildIndexPageData(string $tagslug): array
    {
        $data = [
            'title' => 'Лента статей',
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
                'image' => config('app.url') . '/',
            ]);

            $data['tagInfo'] = $tagFilterService->getByInfoSlug($tagslug);

            if (!empty($data['tagInfo']['id'])) {
                $wikiService = $this->service(WikiService::class);
                $wikiPages = $wikiService->getPagesForTag($data['tagInfo']['id']);
                $data['wikiPages'] = $wikiPages;
                $data['primaryWikiPage'] = $wikiService->getPrimaryPageForTag($data['tagInfo']['id']);
                $data['wikiPagesCount'] = count($wikiPages);
            }
        }

        return $data;
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
            'image' => config('app.url') . '/',
        ]);

$feed = $this->service(StoryFeedBuilder::class)->build(
			tagslug: '',
			author: $username,
			userContext: $userContext,
			pageData: ['title' => $pageTitle]
		);

		return $this->render('index', [
			'stories' => $feed->stories,
			'currentPage' => $feed->currentPage,
			'totalPages' => $feed->totalPages,
			'newCommentsMap' => $feed->newCommentsMap,
			'sort' => $feed->sort,
			'author' => $feed->author,
			'currentUserId' => $feed->currentUserId,
			'isAdmin' => $feed->isAdmin,
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

        $isEmptyState = empty($followedUserIds) && empty($followedTagIds);

        if ($isEmptyState) {
            $stories = [];
            $currentPage = 1;
            $totalPages = 0;
            $newCommentsMap = [];
            $sort = 'new';
        } else {
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

        return $this->render('index', [
            'stories' => $stories,
            'currentPage' => $currentPage ?? 1,
            'totalPages' => $totalPages ?? 0,
            'newCommentsMap' => $newCommentsMap,
            'sort' => $sort ?? 'new',
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'currentVotes' => [],
            'rssFeed' => '',
            'title' => 'Мои подписки',
            'isEmptyState' => $isEmptyState,
        ]);
    }
    
    /**
     * Загрузка изображения для Editor.js с сохранением по папкам с датами.
     */
	public function uploadImage(): JsonResponse
	{
		$userContext = $this->getUserContext();
		if (empty($userContext['isLoggedIn'])) {
			return $this->json(['success' => 0, 'message' => 'Требуется авторизация'], 401);
		}

		try {
			$file = UploadedFile::fromRequest('image');

			$validator = new FileValidator([
				'mimes'      => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
				'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
				'max_size'   => 5 * 1024 * 1024,
			]);
			$validator->validateOrFail($file);

			$storage = $this->container->get(StorageManager::class);
			$datePath = date('Y/m');
			
			// 1. Сохраняем оригинал временно
			$path = $storage->disk('stories')->putFile($file, $datePath);
			$fullPath = $storage->disk('stories')->path($path);

			// 2. Конвертируем в WebP и удаляем оригинал
			$processor = $this->service(\App\Modules\Stories\Services\ImageProcessorService::class);
			$result = $processor->process($fullPath);

			// 3. Возвращаем URL основной версии и реально созданные варианты
			$relativePath = $result['main'];
			$url = $storage->disk('stories')->url($relativePath);

			$variants = [];
			foreach ($result['variants'] as $size => $formats) {
				foreach ($formats as $format => $variantPath) {
					$variants[$size][$format] = $storage->disk('stories')->url($variantPath);
				}
			}

			return $this->json([
				'success' => 1,
				'file' => [
					'url' => $url,
					'variants' => $variants,
				]
			]);

		} catch (ValidationException $e) {
			return $this->json(['success' => 0, 'message' => $e->getMessage()], 400);
		} catch (\Throwable $e) {
			$this->logError($e, 'Stories.uploadImage');
			return $this->json(['success' => 0, 'message' => 'Ошибка при загрузке изображения'], 500);
		}
	}
	
	// =========================================================================
	// API: Трекинг времени чтения (для рекомендаций)
	// =========================================================================
	public function trackReadingTime(): JsonResponse
	{
		// 1. Проверка авторизации
		$userContext = $this->getUserContext();
		if (empty($userContext['isLoggedIn'])) {
			return $this->json(['success' => false], 401);
		}

		// 2. Валидация входных данных
		$storyId = (int)$this->request->post('story_id', 0);
		$seconds = (int)$this->request->post('seconds', 0);

		if ($storyId <= 0 || $seconds <= 0 || $seconds > 3600) {
			return $this->json(['success' => false, 'error' => 'Invalid data'], 400);
		}

		// 3. Защита: пользователь не может читать чужую статью от своего имени больше реального времени
		// (простая проверка — не более 60 секунд за один запрос)
		if ($seconds > 60) {
			$seconds = 60;
		}

		// 4. Проверяем существование статьи
		$storyModel = $this->container->get(Story::class);
		$story = $storyModel->find($storyId);
		if (!$story || !empty($story['deleted_at'])) {
			return $this->json(['success' => false, 'error' => 'Story not found'], 404);
		}

		// 5. Трекаем время
		try {
			$storyView = $this->container->get(\App\Modules\Stories\Models\StoryView::class);
			$storyView->trackReadTime($userContext['id'], $storyId, $seconds);
			
			return $this->json(['success' => true]);
		} catch (\Throwable $e) {
			$this->logError($e, 'Stories.trackReadingTime');
			return $this->json(['success' => false, 'error' => 'Server error'], 500);
		}
	}
	
	// =========================================================================
	// ИСТОРИЯ ЧТЕНИЯ
	// =========================================================================

	public function history(): ViewResponse
	{
		$userContext = $this->getUserContext();
		if ($userContext['id'] <= 0) {
			return $this->redirect('/login');
		}

		$storyView = $this->service(\App\Modules\Stories\Models\StoryView::class);
		$stories = $storyView->getViewedStories($userContext['id'], 50);

		return $this->render('history', [
			'title' => 'История чтения',
			'stories' => $stories,
		]);
	}

	// =========================================================================
	// STAFF PICKS (Выбор редакции)
	// =========================================================================

	/**
	 * Переключить статус "Выбор редакции" для статьи.
	 * Доступно только администраторам.
	 */
	public function toggleStaffPick(string $id): RedirectResponse
	{
		$userContext = $this->getUserContext();
		
		// Двойная проверка прав (middleware + явная проверка)
		if (empty($userContext['isAdmin'])) {
			MessageBag::flashMessage('error', 'Доступ запрещён.');
			return $this->redirectBack();
		}

		$storyId = (int)$id;
		
		// Проверяем существование статьи
		$storyModel = $this->container->get(Story::class);
		$story = $storyModel->find($storyId);
		
		if (!$story || !empty($story['deleted_at'])) {
			MessageBag::flashMessage('error', 'Статья не найдена.');
			return $this->redirectBack();
		}

		try {
			$service = $this->service(\App\Modules\Stories\Services\StaffPicksService::class);
			$result = $service->toggleStaffPick($storyId);
			
			if ($result) {
				// Определяем новое состояние для сообщения
				$newStatus = empty($story['is_staff_pick']); // toggle
				$message = $newStatus 
					? 'Статья добавлена в "Выбор редакции" ⭐' 
					: 'Статья убрана из "Выбор редакции"';
				MessageBag::flashMessage('success', $message);
			} else {
				MessageBag::flashMessage('error', 'Не удалось изменить статус.');
			}
		} catch (\Throwable $e) {
			$this->logError($e, 'Stories.toggleStaffPick');
			MessageBag::flashMessage('error', 'Произошла ошибка при изменении статуса.');
		}

		return $this->redirectBack();
	}
	
	// =========================================================================
	// STAFF PICKS: ОТДЕЛЬНАЯ СТРАНИЦА
	// =========================================================================

	/**
	 * Страница "Выбор редакции" — все статьи с пометкой Staff Pick.
	 */
	public function staffPicks(): ViewResponse
	{
		// Широкий макет для красивой сетки
		Layout::set(Layout::WIDE);

		$currentPage = max(1, (int)$this->request->getParams('page', 1));
		$perPage = 12; // 12 статей на страницу (3 колонки × 4 ряда)
		$offset = ($currentPage - 1) * $perPage;

		$service = $this->service(\App\Modules\Stories\Services\StaffPicksService::class);
		
		$stories = $service->getAllStaffPicks($perPage, $offset);
		$totalCount = $service->getTotalStaffPicksCount();
		$totalPages = (int)ceil($totalCount / $perPage);

		// Получаем голоса пользователя (если авторизован)
		$userContext = $this->getUserContext();
		$currentVotes = [];
		if ($userContext['isLoggedIn'] && !empty($stories)) {
			$storyIds = array_column($stories, 'id');
			$voteModel = $this->container->get(\App\Modules\Votes\Models\Vote::class);
			$userClaps = $voteModel->getUserClapsForStories($userContext['id'], $storyIds);
		}

		// Новые комментарии для прочитанных статей
		$newCommentsMap = [];
		if ($userContext['isLoggedIn'] && !empty($stories)) {
			$storyIds = array_column($stories, 'id');
			$mutedUserIds = $this->service(\App\Modules\Muted\Services\MuteService::class)
				->getMutedUserIds($userContext['id']);
			$readRibbon = $this->container->get(\App\Modules\Stories\Models\ReadRibbon::class);
			$newCommentsMap = $readRibbon->getNewCommentsCounts($userContext['id'], $storyIds, $mutedUserIds);
		}

		return $this->render('staff_picks', [
			'title' => 'Выбор редакции',
			'stories' => $stories,
			'currentPage' => $currentPage,
			'totalPages' => $totalPages,
			'totalCount' => $totalCount,
			'currentUserId' => $userContext['id'],
			'isAdmin' => $userContext['isAdmin'],
			'currentVotes' => $currentVotes,
			'newCommentsMap' => $newCommentsMap,
		]);
	}
	
	
	// =========================================================================
	// ЧЕРНОВИКИ
	// =========================================================================

	/**
	 * Список черновиков пользователя
	 */
	public function drafts(): ViewResponse
	{
		$userContext = $this->getUserContext();

		$page = max(1, (int)$this->request->query('page', 1));

		$data = $this->service(StoryService::class)->getUserDrafts(
			$userContext['id'],
			$page,
			20
		);

		return $this->render('drafts_list', [
			'title' => 'Мои черновики',
			'drafts' => $data['drafts'],
			'total' => $data['total'],
			'currentPage' => $page,
		]);
	}
	
	/**
	 * Создание новой friend link для статьи
	 */
	public function createFriendLink(string $id): JsonResponse
	{
		$userContext = $this->getUserContext();
		if (!$userContext['isLoggedIn']) {
			return $this->json(['error' => 'Требуется авторизация'], 401);
		}

		try {
			$service = $this->service(\App\Modules\Stories\Services\FriendLinkService::class);
			$token = $service->createLink((int)$id, $userContext['id']);
			
			return $this->json([
				'success' => true,
				'token' => $token,
				'url' => config('app.url') . "/story/{$id}?fl={$token}"
			]);
		} catch (\Throwable $e) {
			$this->logError($e, 'Stories.createFriendLink');
			return $this->json(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * Получить список всех friend links для статьи (только для автора)
	 */
	public function getFriendLinks(string $id): JsonResponse
	{
		$userContext = $this->getUserContext();
		if (!$userContext['isLoggedIn']) {
			return $this->json(['error' => 'Требуется авторизация'], 401);
		}

		$storyId = (int)$id;
		
		try {
			$service = $this->service(\App\Modules\Stories\Services\FriendLinkService::class);
			$links = $service->getLinksForStory($storyId, $userContext['id']);
			
			return $this->json([
				'success' => true,
				'links' => $links
			]);
		} catch (\Throwable $e) {
			$this->logError($e, 'Stories.getFriendLinks');
			return $this->json(['error' => 'Ошибка получения ссылок'], 500);
		}
	}

	/**
	 * Удалить (деактивировать) friend link
	 */
	public function deleteFriendLink(string $linkId): RedirectResponse
	{
		$userContext = $this->getUserContext();
		if (!$userContext['isLoggedIn']) {
			MessageBag::flashMessage('error', 'Требуется авторизация.');
			return $this->redirectBack();
		}

		try {
			$service = $this->service(\App\Modules\Stories\Services\FriendLinkService::class);
			$service->deactivateLink((int)$linkId, $userContext['id']);
			
			MessageBag::flashMessage('success', 'Ссылка удалена.');
		} catch (\Throwable $e) {
			MessageBag::flashMessage('error', 'Ошибка удаления ссылки.');
		}

		return $this->redirectBack();
	}
}