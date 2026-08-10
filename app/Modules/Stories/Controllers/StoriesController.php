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
use App\Modules\Content\Core\Markdown;
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
			canUserDownvote: $this->canUserDownvote($userContext['id']),
			pageData: $pageData
		);

		// 3. Формируем ViewModel
		$viewModel = new \App\Modules\Stories\ViewModels\HomeFeedViewModel(
			stories: $feed->stories,
			currentPage: $feed->currentPage,
			totalPages: $feed->totalPages,
			newCommentsMap: $feed->newCommentsMap,
			sort: $feed->sort,
			currentUserId: $feed->currentUserId,
			isAdmin: $feed->isAdmin,
			canUserDownvote: $feed->canUserDownvote,
			currentVotes: $feed->currentVotes,
			forYou: $forYou,
			trending: $trending,
			staffPicks: $staffPicks,
			isLoggedIn: $userContext['isLoggedIn'],
			hasPersonalization: $hasPersonalization,
			pageTitle: $feed->pageTitle,
			rssFeed: $feed->rssFeed,
		);

        // 🔑 Устанавливаем широкий макет для главной
        Layout::set(Layout::WIDE);

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
			canUserDownvote: $this->canUserDownvote($userContext['id']),
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
		$viewModel = $this->service(StoryPageService::class)->buildShowPageData($storyId, $userContext);

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
    // AJAX ENDPOINTS
    // =========================================================================

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

        $feed = $this->service(StoryFeedBuilder::class)->build(
            tagslug: '',
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
            'sort' => $feed->sort,
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
            'canUserDownvote' => $this->canUserDownvote($userContext['id']),
            'currentVotes' => [],
            'rssFeed' => '',
            'title' => 'Мои подписки',
            'isEmptyState' => $isEmptyState,
        ]);
    }
    
    // =========================================================================
    // МИГРАЦИЯ ДАННЫХ (Временный метод, можно удалить после миграции)
    // =========================================================================
    public function migration(): JsonResponse
    {
        $userContext = $this->getUserContext();
        if (empty($userContext['isAdmin'])) {
            return $this->json(['error' => 'Доступ запрещен.'], 403);
        }

        $isDryRun = (string) $this->request->getParams('dry_run', '1') === '1'; 
        $limit = max(1, (int) $this->request->getParams('limit', 10));

        $db = $this->container->get(\W3a\Core\Database\Database::class);
        $logger = $this->container->get(\W3a\Core\Support\Logger::class);
        
        $sanitizer = null;
        if (method_exists($this->container, 'has') && $this->container->has(\W3a\Core\Support\HtmlSanitizer::class)) {
            $sanitizer = $this->container->get(\W3a\Core\Support\HtmlSanitizer::class);
        }

        $migrator = new \App\Modules\Stories\Models\StoryMigrator($db, $logger, $sanitizer);

        try {
            $result = $migrator->processOldStories(dryRun: $isDryRun, limit: $limit);

            if ($isDryRun) {
                return $this->json([
                    'success' => true,
                    'mode' => 'DRY_RUN',
                    'message' => 'Тестовый режим. Данные не сохранены.',
                    'limit' => $limit,
                    'results' => $result
                ]);
            } else {
                return $this->json([
                    'success' => true,
                    'mode' => 'REAL_MIGRATION',
                    'message' => "Успешно мигрировано записей: " . ($result['migrated_count'] ?? 0),
                    'migrated_count' => $result['migrated_count'] ?? 0
                ]);
            }
        } catch (\Throwable $e) {
            $this->logError($e, 'Stories.migration');
            return $this->json(['error' => 'Ошибка миграции: ' . $e->getMessage()], 500);
        }
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
			$webpPath = $processor->process($fullPath);

			if (!$webpPath) {
				// Конвертация не удалась - оставляем оригинал
				$url = $storage->disk('stories')->url($path);
			} else {
				// Возвращаем URL WebP версии
				$relativePath = $storage->disk('stories')->relativePath($webpPath);
				$url = $storage->disk('stories')->url($relativePath);
			}

			return $this->json([
				'success' => 1,
				'file' => [
					'url' => $url,
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
			$currentVotes = $voteModel->getUserVotesForStories($userContext['id'], $storyIds);
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
			'canUserDownvote' => $this->canUserDownvote($userContext['id']),
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
}