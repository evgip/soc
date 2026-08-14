<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use W3a\Core\Foundation\Container;
use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Models\ReadRibbon;
use App\Modules\Tags\Models\Tag;
use App\Modules\Votes\Models\Vote;
use App\Modules\Votes\Services\VoteService; 
use App\Modules\Suggestions\Services\SuggestionService;
use App\Modules\Saved\Models\SavedStory;
use W3a\Core\Exceptions\NotFoundException;
use App\Modules\Stories\ViewModels\StoryShowViewModel;

/**
 * Сервис для сборки данных страницы просмотра истории.
 * Объединяет множество запросов к БД и сервисам в один orchestration-слой.
 */
class StoryPageService
{
    private Container $container;
    private StoryFilterService $storyFilterService;
    private ReadRibbonService $readRibbonService;
    private SuggestionService $suggestionService;
    private VoteService $voteService; 

    public function __construct(
        Container $container,
        StoryFilterService $storyFilterService,
        ReadRibbonService $readRibbonService,
        SuggestionService $suggestionService,
        VoteService $voteService 
    ) {
        $this->container = $container;
        $this->storyFilterService = $storyFilterService;
        $this->readRibbonService = $readRibbonService;
        $this->suggestionService = $suggestionService;
        $this->voteService = $voteService;
    }

    /**
     * Собирает все данные для страницы просмотра истории.
     *
     * @param int $storyId ID истории
     * @param array $userContext Контекст пользователя (id, isLoggedIn, isAdmin, isModerator, isAuthor)
     * @return StoryShowViewModel Строго типизированный объект данных для рендеринга
     * @throws NotFoundException Если история не найдена
     */
	public function buildShowPageData(int $storyId, array $userContext, ?string $friendLinkToken = null): StoryShowViewModel
	{
		// 1. Получаем историю
		$story = $this->storyFilterService->getStoryWithAuthor($storyId);
		if (!$story) {
			throw new NotFoundException("История не найдена.");
		}

		// 2. === НОВАЯ ЛОГИКА: Проверка Friend Link ===
		$hasFriendLinkAccess = false;
		
		if ($friendLinkToken !== null && $friendLinkToken !== '') {
			$friendLinkService = $this->container->get(
				\App\Modules\Stories\Services\FriendLinkService::class
			);
			$hasFriendLinkAccess = $friendLinkService->checkAccess($friendLinkToken, $storyId);
		}

		// 3. === НОВАЯ ЛОГИКА: Проверка доступа к пейволлу ===
		$canSeeFullContent = true; // По умолчанию полный доступ
		
		if (!empty($story['has_paywall']) && !$hasFriendLinkAccess) {
			// Есть пейволл и нет friend link доступа
			if (!$userContext['isLoggedIn']) {
				// Гость видит только превью
				$canSeeFullContent = false;
			} else {
				$userId = $userContext['id'];
				$isAuthorCheck = (int)$story['user_id'] === $userId;
				
				if (!$isAuthorCheck) {
					// Не автор - проверяем тип пейволла
					switch ($story['paywall_type']) {
						case 'members':
							// Любой залогиненный пользователь имеет доступ
							$canSeeFullContent = true;
							break;
						case 'subscribers':
							// Только подписчики автора
							$subscriptionService = $this->container->get(
								\App\Modules\Subscriptions\Services\SubscriptionService::class
							);
							$canSeeFullContent = $subscriptionService->isFollowingUser(
								$userId, 
								(int)$story['user_id']
							);
							break;
						default:
							$canSeeFullContent = true;
					}
				}
			}
		}

		// 4. Получаем дерево комментариев
		$commentsTree = $this->storyFilterService->getCommentsTree($storyId);

		// 4.1 Выделение
		$highlightModel = $this->container->get(\App\Modules\Comments\Models\CommentHighlight::class);
		$highlights = $highlightModel->getByStory($storyId);

		// Создаём карту comment_id → quoted_text
		$highlightMap = [];
		foreach ($highlights as $h) {
			$highlightMap[(int)$h['comment_id']] = $h['quoted_text'];
		}

		// Рекурсивно добавляем highlight в каждый комментарий
		$addHighlights = function(array &$tree) use (&$addHighlights, $highlightMap) {
			foreach ($tree as &$branch) {
				foreach ($branch as &$comment) {
					if (isset($highlightMap[(int)$comment['id']])) {
						$comment['highlight'] = $highlightMap[(int)$comment['id']];
					}
				}
			}
		};
		$addHighlights($commentsTree);


		// 4.2 === Коллекции, в которых есть эта статья (для навигации) ===
		$collectionItemModel = $this->container->get(\App\Modules\Collections\Models\CollectionItem::class);
		$storyCollections = $collectionItemModel->getCollectionsForStory($storyId);

		// Добавляем prev/next для каждой коллекции
		foreach ($storyCollections as &$c) {
			$prevNext = $collectionItemModel->getPrevNextStories((int) $c['collection_id'], $storyId);
			$c['prev'] = $prevNext['prev'];
			$c['next'] = $prevNext['next'];
		}
		unset($c);

		// 5. Read Ribbon (лента прочтения)
		$readRibbonModel = $this->container->get(ReadRibbon::class);
		$ribbonData = $readRibbonModel->getForStories($userContext['id'], [$storyId]);
		$lastReadCommentId = $ribbonData[$storyId] ?? 0;

		// Обновляем счетчик новых комментариев
		$newCommentsCount = $this->readRibbonService->handleStoryView($storyId);

		// 6. Suggestions (предложения по улучшению)
		$activeSuggestions = $this->suggestionService->getActiveSuggestions('Story', $storyId);
		$changeLog = $this->suggestionService->getChangeLog('Story', $storyId, 10);

		// 7. Теги
		$tagModel = $this->container->get(Tag::class);
		$allTags = $tagModel->getAllTags();

		$storyModel = $this->container->get(Story::class);
		$currentTagIds = $storyModel->getStoryTagIds($storyId);

		// 8. Данные, зависящие от авторизации
		$currentStoryVote = null;
		$currentCommentVotes = [];
		$userSuggestionsCount = 0;
		$isAuthor = false;
		$isStorySaved = false;
		$canUserDownvote = false;

		if ($userContext['isLoggedIn']) {
			$userId = $userContext['id'];
			$voteModel = $this->container->get(Vote::class);

			// Получаем голос пользователя за историю
			$currentStoryVote = $voteModel->getUserVote($userId, 'story', $storyId);

			// Собираем ID всех комментариев для получения голосов (Batch-запрос)
			$allCommentIds = [];
			foreach ($commentsTree as $comments) {
				foreach ($comments as $comment) {
					$allCommentIds[] = (int)$comment['id'];
				}
			}

			if (!empty($allCommentIds)) {
				$currentCommentVotes = $voteModel->getUserVotesForComments($userId, $allCommentIds);
			}

			// Проверяем, является ли пользователь автором истории (используем callback из контекста)
			$isAuthor = $userContext['isAuthor']((int)$story['user_id']);

			// Получаем количество активных предложений от пользователя (если не модератор/админ)
			if (!$userContext['isModerator'] && !$userContext['isAdmin']) {
				$userSuggestionsCount = $this->suggestionService->getUserActiveSuggestionsCount('Story', $storyId, $userId);
			}

			// Проверяем, сохранена ли история
			$savedModel = $this->container->get(SavedStory::class);
			$isStorySaved = $savedModel->isSaved($userId, $storyId);
			
			// Проверяем право на голосование вниз (теперь это делает сервис, а не контроллер)
			$canUserDownvote = $this->voteService->canUserDownvote($userId);
		}

		// 9. Возвращаем строго типизированный ViewModel вместо массива
		return new StoryShowViewModel(
			story: $story,
			commentsTree: $commentsTree,
			currentCommentVotes: $currentCommentVotes,
			currentUserId: $userContext['id'],
			isAdmin: $userContext['isAdmin'],
			isModerator: $userContext['isModerator'],
			isAuthor: $isAuthor,
			canUserDownvote: $canUserDownvote,
			currentStoryVote: $currentStoryVote,
			isStorySaved: $isStorySaved,
			userSuggestionsCount: $userSuggestionsCount,
			maxSuggestionsAllowed: SuggestionService::MAX_USER_SUGGESTIONS,
			activeSuggestions: $activeSuggestions,
			changeLog: $changeLog,
			allTags: $allTags,
			currentTagIds: $currentTagIds,
			newCommentsCount: $newCommentsCount,
			lastReadCommentId: $lastReadCommentId,
			// === для Friend Links ===
			canSeeFullContent: $canSeeFullContent,
			hasFriendLinkAccess: $hasFriendLinkAccess,
			// === для Collections ===
			storyCollections: $storyCollections,
		);
	}
}