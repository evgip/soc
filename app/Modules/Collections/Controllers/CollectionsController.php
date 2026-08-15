<?php

declare(strict_types=1);

namespace App\Modules\Collections\Controllers;

use App\BaseController;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Http\JsonResponse;
use W3a\Core\Http\Response;
use W3a\Core\Support\MessageBag;
use W3a\Core\Exceptions\NotFoundException;
use W3a\Core\Security\RateLimiter;
use W3a\Core\Exceptions\RateLimitExceededException;

use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\CollectionItem;
use App\Modules\Collections\Services\CollectionService;
use App\Modules\Collections\Services\CollectionCoverService;  
use App\Modules\Collections\Exceptions\CollectionValidationException;
use App\Modules\Users\Models\User;
use App\Modules\Stories\Models\Story;

use App\Modules\Common\Support\Layout; 

/**
 * Контроллер коллекций (серий статей).
 * 
 * Управляет CRUD-операциями над коллекциями:
 * - Создание, редактирование, удаление коллекций
 * - Добавление/удаление статей в коллекцию
 * - Изменение порядка статей (drag-and-drop)
 * - Загрузка и удаление обложек коллекций
 * 
 * Rate limiting: создание коллекции ограничено (см. config/rate_limit.php).
 */
class CollectionsController extends BaseController
{
    // =========================================================================
    // ПУБЛИЧНЫЕ СТРАНИЦЫ
    // =========================================================================

    /**
     * Список коллекций пользователя.
     * URL: /collections/{username}
     * 
     * Показывает все коллекции автора. Приватные коллекции видны только владельцу.
     * Для каждой коллекции формируется cover_url через CollectionCoverService.
     */
    public function index(string $username): ViewResponse
    {
        $userModel = $this->container->get(User::class);
        $user = $userModel->findByName($username);

        if (!$user) {
            throw new NotFoundException("Пользователь не найден");
        }

        $collectionModel = $this->container->get(Collection::class);
        $collections = $collectionModel->getByAuthor((int) $user['id']);

        $userContext = $this->getUserContext();
        $isOwner = $userContext['isLoggedIn'] && $userContext['id'] === (int) $user['id'];

        // Фильтруем приватные коллекции для не-владельцев
        if (!$isOwner) {
            $collections = array_filter($collections, fn($c) => !empty($c['is_public']));
            $collections = array_values($collections);
        }

        // Формируем полные URL обложек для шаблона
        $coverService = $this->service(CollectionCoverService::class);
        foreach ($collections as &$collection) {
            $collection['cover_url'] = !empty($collection['cover_image'])
                ? $coverService->getCoverUrl($collection['cover_image'])
                : null;
        }
        unset($collection);

        return $this->render('index', [
            'title' => 'Коллекции ' . e($username),
            'profileUser' => $user,
            'collections' => $collections,
            'isOwner' => $isOwner,
        ]);
    }

    /**
     * Страница коллекции с оглавлением.
     * URL: /collections/{username}/{slug}
     * 
     * Отображает информацию о коллекции и список статей с номерами.
     * Для каждой статьи формируется excerpt (краткое описание).
     */
    public function show(string $username, string $slug): ViewResponse
    {
        $userModel = $this->container->get(User::class);
        $user = $userModel->findByName($username);

        if (!$user) {
            throw new NotFoundException("Пользователь не найден");
        }

        $service = $this->service(CollectionService::class);
        $collection = $service->getCollectionWithStories((int) $user['id'], $slug);

        if (!$collection) {
            throw new NotFoundException("Коллекция не найдена");
        }

        // Формируем полный URL обложки
        $coverService = $this->service(CollectionCoverService::class);
        $collection['cover_url'] = !empty($collection['cover_image'])
            ? $coverService->getCoverUrl($collection['cover_image'])
            : null;

        $userContext = $this->getUserContext();
        $isOwner = $userContext['isLoggedIn'] && $userContext['id'] === (int) $collection['author_id'];

		// Проверяем подписку на коллекцию
		$isFollowingCollection = false;
		$followersCount = 0;
		if ($userContext['isLoggedIn'] && !$isOwner) {
			$subscriptionService = $this->service(\App\Modules\Subscriptions\Services\SubscriptionService::class);
			$isFollowingCollection = $subscriptionService->isFollowingCollection(
				$userContext['id'], 
				(int) $collection['id']
			);
		}
		
		// Считаем подписчиков для всех (кроме приватных для не-владельцев)
		if (!empty($collection['is_public']) || $isOwner) {
			$subscriptionService = $subscriptionService 
				?? $this->service(\App\Modules\Subscriptions\Services\SubscriptionService::class);
			$followersCount = $subscriptionService->getCollectionFollowersCount((int) $collection['id']);
		}


        return $this->render('show', [
            'title' => e($collection['title']),
            'collection' => $collection,
            'profileUser' => $user,
            'isOwner' => $isOwner,
			'isFollowingCollection' => $isFollowingCollection,
			'followersCount' => $followersCount,  
        ]);
    }

    // =========================================================================
    // CRUD (формы и обработка)
    // =========================================================================

    /**
     * Форма создания коллекции.
     * URL: GET /collections/create
     */
    public function showCreateForm(): ViewResponse
    {
        return $this->render('create', [
            'title' => 'Новая коллекция',
        ]);
    }

    /**
     * Создание коллекции (обработка формы).
     * URL: POST /collections
     * 
     * Rate limited: максимум 3 коллекции в сутки (config/rate_limit.php).
     * Лимит проверяется после валидации формы, до создания записи в БД.
     */
    public function create(): RedirectResponse
    {
        $userContext = $this->getUserContext();

        $data = [
            'title'       => (string) $this->request->getParams('title'),
            'description' => (string) $this->request->getParams('description'),
            'is_public'   => $this->request->getParams('is_public'),
        ];

        // Rate limiting (после получения данных, до создания)
        // Лимит тратится только на валидные попытки
        try {
            $this->container->get(RateLimiter::class)->check('collection.create');
        } catch (RateLimitExceededException $e) {
            MessageBag::flashMessage('error', 'Превышен лимит: максимум 3 коллекции в сутки.');
            return $this->redirectBack();
        }

        try {
            $collectionId = $this->service(CollectionService::class)->createCollection($data);
        } catch (CollectionValidationException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack();
        } catch (\Throwable $e) {
            $this->logError($e, 'Collections.create');
            MessageBag::flashMessage('error', 'Произошла ошибка при создании коллекции.');
            return $this->redirectBack();
        }

        // Загружаем обложку (если файл был загружен)
        $this->uploadCoverForCollection($collectionId);

        MessageBag::flashMessage('success', 'Коллекция успешно создана!');
        
        $username = $this->getCurrentUsername();
        $slug = $this->getCollectionSlug($collectionId);
        
        if ($username === '' || $slug === '') {
            return $this->redirect('/collections');
        }
        
        return $this->redirect('/collections/' . $username . '/' . $slug);
    }

    /**
     * Форма редактирования коллекции.
     * URL: GET /collections/{id}/edit
     * 
     * Доступна только владельцу коллекции или админу.
     */
    public function showEditForm(string $id): Response
    {
        $collectionId = (int) $id;
        $collectionModel = $this->container->get(Collection::class);
        $collection = $collectionModel->find($collectionId);

        $userContext = $this->getUserContext();

        if (!$collection || !empty($collection['deleted_at']) ||
            ((int) $collection['author_id'] !== $userContext['id'] && !$userContext['isAdmin'])) {
            MessageBag::flashMessage('error', 'У вас нет прав для редактирования этой коллекции.');
            return $this->redirectBack();
        }

        // URL текущей обложки (для превью в форме)
        $coverUrl = null;
        if (!empty($collection['cover_image'])) {
            $coverService = $this->service(CollectionCoverService::class);
            $coverUrl = $coverService->getCoverUrl($collection['cover_image']);
        }

        return $this->render('edit', [
            'title' => 'Редактирование коллекции',
            'collection' => $collection,
            'coverUrl' => $coverUrl,
        ]);
    }

    /**
     * Обновление коллекции.
     * URL: POST /collections/{id}
     * 
     * Также обрабатывает загрузку/удаление обложки (флаги в форме).
     */
    public function update(string $id): RedirectResponse
    {
        $collectionId = (int) $id;
        $userContext = $this->getUserContext();

        $data = [
            'title'       => (string) $this->request->getParams('title'),
            'description' => (string) $this->request->getParams('description'),
            'is_public'   => $this->request->getParams('is_public'),
        ];

        try {
            $collection = $this->service(CollectionService::class)->updateCollection($collectionId, $data);
        } catch (CollectionValidationException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack();
        } catch (\Throwable $e) {
            $this->logError($e, 'Collections.update');
            MessageBag::flashMessage('error', 'Произошла ошибка при обновлении коллекции.');
            return $this->redirectBack();
        }

        // Обрабатываем действия с обложкой
        $deleteCover = $this->request->getParams('delete_cover') === '1';
        if ($deleteCover) {
            $this->deleteCoverForCollection($collection);
        } else {
            $this->uploadCoverForCollection($collectionId, $collection['cover_image'] ?? null);
        }

        MessageBag::flashMessage('success', 'Коллекция обновлена.');
        
        $username = $this->getAuthorName((int) $collection['author_id']);
        $slug = $collection['slug'];
        
        if ($username === '' || $slug === '') {
            return $this->redirect('/collections');
        }
        
        return $this->redirect('/collections/' . $username . '/' . $slug);
    }

    /**
     * Удаление коллекции (мягкое).
     * URL: POST /collections/{id}/delete
     */
    public function delete(string $id): RedirectResponse
    {
        $collectionId = (int) $id;

        try {
            $collection = $this->service(CollectionService::class)->deleteCollection($collectionId);
        } catch (CollectionValidationException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack();
        } catch (\Throwable $e) {
            $this->logError($e, 'Collections.delete');
            MessageBag::flashMessage('error', 'Произошла ошибка при удалении коллекции.');
            return $this->redirectBack();
        }

        MessageBag::flashMessage('success', 'Коллекция удалена.');
        return $this->redirect('/collections/' . $this->getAuthorName((int)$collection['author_id']));
    }

    // =========================================================================
    // AJAX: УПРАВЛЕНИЕ СТАТЬЯМИ В КОЛЛЕКЦИИ
    // =========================================================================

	/**
	 * Добавить статью в коллекцию.
	 * URL: POST /collections/{id}/stories/add
	 * 
	 * После успешного добавления уведомляет всех подписчиков коллекции.
	 */
	public function addStory(string $id): JsonResponse
	{
		$collectionId = (int) $id;
		$storyId = (int) $this->request->post('story_id', 0);

		if ($storyId <= 0) {
			return $this->json(['success' => false, 'error' => 'Некорректный ID статьи'], 400);
		}

		try {
			$this->service(CollectionService::class)->addStory($collectionId, $storyId);

			// Уведомляем подписчиков коллекции о новой части
			$this->notifyCollectionSubscribers($collectionId, $storyId);

			return $this->json(['success' => true]);
		} catch (CollectionValidationException $e) {
			return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
		} catch (\Throwable $e) {
			$this->logError($e, 'Collections.addStory');
			return $this->json(['success' => false, 'error' => 'Ошибка сервера'], 500);
		}
	}

	/**
	 * Отправить уведомления подписчикам коллекции о новой части.
	 * 
	 * Вспомогательный метод для addStory(). Ошибки логируются,
	 * но не прерывают основное действие (добавление статьи уже успешно).
	 */
	private function notifyCollectionSubscribers(int $collectionId, int $storyId): void
	{
		try {
			$collectionModel = $this->container->get(Collection::class);
			$collection = $collectionModel->find($collectionId);

			if (!$collection) {
				return;
			}

			$storyModel = $this->container->get(Story::class);
			$story = $storyModel->find($storyId);

			if (!$story) {
				return;
			}

			$notificationService = $this->service(\App\Modules\Notifications\Services\NotificationService::class);
			$notificationService->notifyCollectionSubscribers(
				$collectionId,
				$storyId,
				(int) $collection['author_id'],
				(string) $collection['title'],
				(string) $story['title']
			);
		} catch (\Throwable $e) {
			// Не прерываем добавление статьи из-за ошибки уведомлений
			$this->logError($e, 'Collections.notifySubscribers');
		}
	}

    /**
     * Удалить статью из коллекции.
     * URL: POST /collections/{id}/stories/remove
     */
    public function removeStory(string $id): JsonResponse
    {
        $collectionId = (int) $id;
        $storyId = (int) $this->request->post('story_id', 0);

        try {
            $this->service(CollectionService::class)->removeStory($collectionId, $storyId);
            return $this->json(['success' => true]);
        } catch (CollectionValidationException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logError($e, 'Collections.removeStory');
            return $this->json(['success' => false, 'error' => 'Ошибка сервера'], 500);
        }
    }

    /**
     * Изменить порядок статей в коллекции (drag-and-drop).
     * URL: POST /collections/{id}/stories/reorder
     * 
     * @param array ordered_ids Массив story_id в новом порядке
     */
    public function reorderStories(string $id): JsonResponse
    {
        $collectionId = (int) $id;
        $orderedIds = $this->request->post('ordered_ids', []);

        if (!is_array($orderedIds)) {
            return $this->json(['success' => false, 'error' => 'Некорректные данные'], 400);
        }

        try {
            $this->service(CollectionService::class)->reorderStories($collectionId, $orderedIds);
            return $this->json(['success' => true]);
        } catch (CollectionValidationException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logError($e, 'Collections.reorder');
            return $this->json(['success' => false, 'error' => 'Ошибка сервера'], 500);
        }
    }

    /**
     * Список доступных статей автора (для модального окна добавления).
     * URL: GET /collections/{id}/stories/available
     * 
     * Возвращает все опубликованные статьи автора с флагом in_collection
     * (находится ли статья уже в данной коллекции).
     */
    public function availableStories(string $id): JsonResponse
    {
        $collectionId = (int) $id;

        $collectionModel = $this->container->get(Collection::class);
        $collection = $collectionModel->find($collectionId);

        $userContext = $this->getUserContext();

        if (!$collection || (int) $collection['author_id'] !== $userContext['id']) {
            return $this->json(['success' => false, 'error' => 'Нет доступа'], 403);
        }

        // Все опубликованные статьи автора
        $storyModel = $this->container->get(Story::class);
        $stories = $storyModel->getPublishedByAuthor($userContext['id']);

        // Отмечаем какие уже в коллекции
        $itemModel = $this->container->get(CollectionItem::class);
        foreach ($stories as &$story) {
            $story['in_collection'] = $itemModel->isStoryInCollection($collectionId, (int) $story['id']);
        }
        unset($story);

        return $this->json(['success' => true, 'stories' => $stories]);
    }

    /**
     * Список коллекций текущего автора (для dropdown на странице статьи).
     * URL: GET /collections/my/list
     * 
     * Опционально принимает story_id — если передан, для каждой коллекции
     * добавляется флаг has_story (есть ли статья в этой коллекции).
     */
    public function myCollectionsList(): JsonResponse
    {
        $userContext = $this->getUserContext();
        $storyId = (int) $this->request->getParams('story_id', 0);

        $collectionModel = $this->container->get(Collection::class);
        $collections = $collectionModel->getByAuthor($userContext['id']);

        // Если передан story_id — отмечаем в каких коллекциях статья уже есть
        if ($storyId > 0) {
            $itemModel = $this->container->get(CollectionItem::class);
            foreach ($collections as &$c) {
                $c['has_story'] = $itemModel->isStoryInCollection((int) $c['id'], $storyId);
            }
            unset($c);
        }

        return $this->json(['success' => true, 'collections' => $collections]);
    }

    // =========================================================================
    // ПРИВАТНЫЕ ХЕЛПЕРЫ
    // =========================================================================

    /**
     * Получить slug коллекции по ID (для редиректа после создания).
     */
    private function getCollectionSlug(int $collectionId): string
    {
        $collection = $this->container->get(Collection::class)->find($collectionId);
        return $collection['slug'] ?? '';
    }

    /**
     * Получить username автора по ID (для редиректа).
     * 
     * Логирует ошибку если пользователь не найден (аномальная ситуация).
     */
    private function getAuthorName(int $authorId): string
    {
        if ($authorId <= 0) {
            return '';
        }
        
        $user = $this->container->get(User::class)->find($authorId);
        
        if (!$user || empty($user['username'])) {
            $this->logError(
                new \RuntimeException("Username not found for author_id={$authorId}"), 
                'Collections.getAuthorName'
            );
            return '';
        }
        
        return $user['username'];
    }
    
    /**
     * Получить username текущего авторизованного пользователя.
     */
    private function getCurrentUsername(): string
    {
        $userContext = $this->getUserContext();
        
        if (empty($userContext['id'])) {
            return '';
        }
        
        $user = $this->container->get(User::class)->find($userContext['id']);
        return $user['username'] ?? '';
    }
    
    /**
     * Загрузить обложку для коллекции (если файл был в запросе).
     * 
     * Использует CollectionCoverService для валидации, конвертации и сохранения.
     * Автоматически удаляет старую обложку при замене.
     * 
     * @param int $collectionId ID коллекции
     * @param string|null $oldCover Путь к старой обложке (для удаления)
     * @return string|null Путь к новой обложке или null если не загружалась
     */
    private function uploadCoverForCollection(int $collectionId, ?string $oldCover = null): ?string
    {
        $coverFile = $this->request->file('cover_file');
        
        if (!$coverFile || ($coverFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        try {
            $coverService = $this->service(CollectionCoverService::class);
            $coverPath = $coverService->handleUpload($coverFile, $oldCover);

            // Обновляем поле cover_image в БД
            $collectionModel = $this->container->get(Collection::class);
            $collectionModel->update($collectionId, ['cover_image' => $coverPath]);

            return $coverPath;
        } catch (CollectionValidationException $e) {
            MessageBag::flashMessage('error', 'Обложка: ' . $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            $this->logError($e, 'Collections.uploadCover');
            MessageBag::flashMessage('error', 'Не удалось загрузить обложку.');
            return null;
        }
    }

    /**
     * Удалить обложку коллекции.
     * 
     * Удаляет файл с диска через CollectionCoverService и обнуляет поле в БД.
     * Ошибки логируются, но не прерывают выполнение (некритичная операция).
     */
    private function deleteCoverForCollection(array $collection): void
    {
        if (empty($collection['cover_image'])) {
            return;
        }

        try {
            $coverService = $this->service(CollectionCoverService::class);
            $coverService->deleteCover($collection['cover_image']);

            // Обнуляем поле cover_image в БД
            $collectionModel = $this->container->get(Collection::class);
            $collectionModel->update((int) $collection['id'], ['cover_image' => null]);
        } catch (\Throwable $e) {
            $this->logError($e, 'Collections.deleteCover');
        }
    }
}