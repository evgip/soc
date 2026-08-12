<?php

declare(strict_types=1);

namespace App\Modules\Comments\Controllers;

use App\BaseController;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Http\JsonResponse;
use W3a\Core\Http\Response;
use W3a\Core\Support\MessageBag;
use W3a\Core\Exceptions\NotFoundException;

use App\Modules\Comments\Exceptions\CommentValidationException;
use App\Modules\Comments\Exceptions\CommentPermissionException;
use App\Modules\Comments\Services\CommentService;
use App\Modules\Votes\Models\Vote;
use App\Modules\Users\Models\User;
use App\Modules\Stories\Services\ReadRibbonService;

class CommentsController extends BaseController
{
    /**
     * Глобальная лента всех комментариев
     */
	public function index(): ViewResponse
	{
		$userContext = $this->getUserContext();

		$lastReadAt = null;
		if ($userContext['isLoggedIn']) {
			$userModel = $this->container->get(User::class);
			$user = $userModel->find($userContext['id']);
			if ($user) {
				$lastReadAt = $user['last_read_comments_at'] ?? null;
			}
			$userModel->updateLastReadComments($userContext['id']);
		}

		$commentService = $this->service(CommentService::class);
		$comments = $commentService->getLatestComments(50);

		if ($userContext['isLoggedIn'] && !empty($comments)) {
			$readRibbonService = $this->service(ReadRibbonService::class);
			$storyIds = collect($comments)
						->pluck('story_id')
						->unique()
						->values()
						->toArray();
			$readRibbonService->markStoriesAsRead($storyIds);
		}

		$currentCommentVotes = [];
		if ($userContext['isLoggedIn'] && !empty($comments)) {
			$voteModel = $this->container->get(Vote::class);
			$commentIds = collect($comments)->pluck('id')->map(fn($id) => (int) $id)->toArray();
			$currentCommentVotes = $voteModel->getUserVotesForComments($userContext['id'], $commentIds);
		}

		$highlightMap = [];
		if (!empty($comments)) {
			$highlightModel = $this->container->get(\App\Modules\Comments\Models\CommentHighlight::class);
			$commentIds = array_map(fn($c) => (int)$c['id'], $comments);
			$highlights = $highlightModel->getByCommentIds($commentIds);
			$highlightMap = array_column($highlights, 'quoted_text', 'comment_id');
		}

		$canDownvote = $this->canUserDownvote($userContext['id']);

		return $this->render('index', [
			'comments' => $comments,
			'lastReadAt' => $lastReadAt,
			'currentUserId' => $userContext['id'],
			'isAdmin' => $userContext['isAdmin'],
			'isModerator' => $userContext['isModerator'],
			'canDownvote' => $canDownvote,
			'currentCommentVotes' => $currentCommentVotes,
			'highlightMap' => $highlightMap,  // 🆕 Передаём карту
			'title' => 'Последние комментарии',
			'rssFeed' => [
				'title' => 'Новые комментарии',
				'url' => '/comments/rss',
			],
		]);
	}

    /**
     * Создание комментария
     */
	public function create(): RedirectResponse
	{
		$storyId = (int)$this->request->getParams('story_id');
		$parentIdRaw = $this->request->getParams('parent_id');
		$commentText = (string)$this->request->getParams('comment_text');
		
		// Проверяем, есть ли данные для inline-комментария
		$highlightQuote = trim((string)$this->request->getParams('highlight_quote'));
		$isInline = !empty($highlightQuote);

		if ($parentIdRaw === null || $parentIdRaw === '' || $parentIdRaw === '0' || (int)$parentIdRaw <= 0) {
			$parentId = null;
		} else {
			$parentId = (int)$parentIdRaw;
		}

		$commentId = null;

		try {
			$commentService = $this->service(CommentService::class);
			
			// Создаём комментарий (обычный или inline — решает сервис)
			$commentId = $commentService->createComment($storyId, $commentText, $parentId);
			
			// Если это inline-комментарий — сохраняем highlight
			if ($isInline && $commentId > 0) {
				$highlightModel = $this->container->get(\App\Modules\Comments\Models\CommentHighlight::class);
				$highlightModel->saveHighlight([
					'comment_id'   => $commentId,
					'story_id'     => $storyId,
					'quoted_text'  => $highlightQuote,
					'block_index'  => $this->request->getParams('highlight_block_index'),
					'block_type'   => $this->request->getParams('highlight_block_type'),
					'start_offset' => $this->request->getParams('highlight_start_offset'),
					'end_offset'   => $this->request->getParams('highlight_end_offset'),
				]);
			}
		} catch (CommentValidationException $e) {
			MessageBag::flashMessage('error', $e->getMessage());
			return $this->redirect("/story/{$storyId}");
		} catch (\Throwable $e) {
			$this->logError($e, 'Comments.create');
			MessageBag::flashMessage('error', 'Произошла ошибка при создании комментария.');
			return $this->redirect("/story/{$storyId}");
		}

		MessageBag::flashMessage('success', 'Ваш комментарий успешно опубликован!');
		return $this->redirect(comment_url($storyId, $commentId));
	}

    /**
     * Редактирование комментария (поддерживает AJAX и обычный POST)
     */
    public function edit(string $id): JsonResponse|RedirectResponse
    {
        $commentId = (int)$id;
        $newText = (string)$this->request->getParams('comment_text');
        $isAjax = $this->request->isAjaxRequest();

        $result = null;

        // 1. Пытаемся выполнить бизнес-логику
        try {
            $result = $this->service(CommentService::class)->updateComment($commentId, $newText);
        } 
        // 2. Ловим ожидаемые бизнес-ошибки
        catch (CommentValidationException | CommentPermissionException | \InvalidArgumentException $e) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
            }
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack();
        } 
        // 3. Ловим непредвиденные ошибки и логируем их
        catch (\Throwable $e) {
            $this->logError($e, 'Comments.edit');
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Внутренняя ошибка сервера'], 500);
            }
            MessageBag::flashMessage('error', 'Произошла непредвиденная ошибка.');
            return $this->redirectBack();
        }

        // 4. Успешное выполнение
        if ($isAjax) {
            $html = markdown_comment($result['comment']['comment']);
            
            return $this->json([
                'success' => true,
                'html' => $html,
                'raw' => $result['comment']['comment']
            ]);
        }

        MessageBag::flashMessage('success', 'Комментарий успешно обновлён.');
        return $this->redirect(comment_url((int)$result['comment']['story_id'], $commentId));
    }

    /**
     * Удаление комментария
     */
    public function delete(string $id): RedirectResponse
    {
        $commentId = (int)$id;
        $result = null;

        try {
            $result = $this->service(CommentService::class)->deleteComment($commentId);
        } catch (CommentPermissionException | \InvalidArgumentException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack();
        } catch (\Throwable $e) {
            $this->logError($e, 'Comments.delete');
            MessageBag::flashMessage('error', 'Произошла ошибка при удалении.');
            return $this->redirectBack();
        }

        MessageBag::flashMessage('success', 'Комментарий успешно удален.');
        return $this->redirect(comment_url($result['story_id'], $commentId));
    }

    /**
     * Восстановление комментария
     */
    public function restore(string $id): RedirectResponse
    {
        $commentId = (int)$id;
        $result = null;

        try {
            $result = $this->service(CommentService::class)->restoreComment($commentId);
        } catch (CommentValidationException | CommentPermissionException | \InvalidArgumentException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack();
        } catch (\Throwable $e) {
            $this->logError($e, 'Comments.restore');
            MessageBag::flashMessage('error', 'Произошла ошибка при восстановлении.');
            return $this->redirectBack();
        }

        MessageBag::flashMessage('success', 'Комментарий успешно восстановлен.');
        return $this->redirect(comment_url($result['story_id'], $commentId));
    }

    /**
     * Комментарии конкретного пользователя
     */
    public function userComments(string $username): ViewResponse
    {
        $userModel = $this->container->get(User::class);
        $user = $userModel->findByName($username);

        if (!$user) {
            throw new NotFoundException("Пользователь не найден");
        }

        $comments = $this->service(CommentService::class)->getUserComments((int)$user['id'], 50);
        $userContext = $this->getUserContext();

        return $this->render('user_comments', [
            'profileUser' => $user,
            'comments' => $comments,
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'isModerator' => $userContext['isModerator'],
            'title' => 'Комментарии пользователя ' . e($username),
        ]);
    }
	
	/**
	 * Создание inline-комментария (с привязкой к выделенному фрагменту)
	 */
	public function createWithHighlight(): JsonResponse
	{
		$storyId = (int)$this->request->getParams('story_id');
		$commentText = (string)$this->request->getParams('comment_text');
		
		$highlight = [
			'quoted_text'  => (string)$this->request->getParams('quoted_text'),
			'block_index'  => $this->request->getParams('block_index'),
			'block_type'   => $this->request->getParams('block_type'),
			'start_offset' => $this->request->getParams('start_offset'),
			'end_offset'   => $this->request->getParams('end_offset'),
		];

		try {
			$commentId = $this->service(CommentService::class)
				->createCommentWithHighlight($storyId, $commentText, $highlight);
			
			return $this->json([
				'success' => true,
				'comment_id' => $commentId,
			]);
		} catch (CommentValidationException $e) {
			return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
		} catch (\Throwable $e) {
			$this->logError($e, 'Comments.createWithHighlight');
			return $this->json(['success' => false, 'error' => 'Ошибка сервера'], 500);
		}
	}

	/**
	 * Получить все highlights для статьи (для подсветки при загрузке)
	 */
	public function highlights(string $storyId): JsonResponse
	{
		try {
			$highlights = $this->service(CommentService::class)
				->getHighlightsForStory((int)$storyId);
			
			return $this->json([
				'success' => true,
				'highlights' => $highlights,
			]);
		} catch (\Throwable $e) {
			$this->logError($e, 'Comments.highlights');
			return $this->json(['success' => false, 'highlights' => []]);
		}
	}
}