<?php

declare(strict_types=1);

namespace App\Modules\Comments\Controllers;

use App\BaseController;
use W3a\Core\Http\Session;
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
    public function index(): void
    {
        $userContext = $this->getUserContext(); // Предполагаем, что это массив из базового контроллера

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
            $storyIds = array_unique(array_column($comments, 'story_id'));
            $readRibbonService->markStoriesAsRead($storyIds);
        }

        $currentCommentVotes = [];
        if ($userContext['isLoggedIn'] && !empty($comments)) {
            $voteModel = $this->container->get(Vote::class);
            $commentIds = array_map('intval', array_column($comments, 'id'));
            $currentCommentVotes = $voteModel->getUserVotesForComments($userContext['id'], $commentIds);
        }

        $canDownvote = $this->canUserDownvote($userContext['id']);

        $this->render('index', [
            'comments' => $comments,
            'lastReadAt' => $lastReadAt,
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'isModerator' => $userContext['isModerator'],
            'canDownvote' => $canDownvote,
            'currentCommentVotes' => $currentCommentVotes,
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
    public function create(): void
    {
        $storyId = (int)$this->request->getParams('story_id');
        $parentIdRaw = $this->request->getParams('parent_id');
        $commentText = (string)$this->request->getParams('comment_text');

        if ($parentIdRaw === null || $parentIdRaw === '' || $parentIdRaw === '0' || (int)$parentIdRaw <= 0) {
            $parentId = null;
        } else {
            $parentId = (int)$parentIdRaw;
        }

        $commentId = null;

        try {
            $commentId = $this->service(CommentService::class)->createComment($storyId, $commentText, $parentId);
        } catch (CommentValidationException $e) {
            $this->session()->flash('error', $e->getMessage());
            $this->redirect("/story/{$storyId}");
            return;
        } catch (\Throwable $e) {
            $this->logError($e, 'Comments.create');
            $this->session()->flash('error', 'Произошла ошибка при создании комментария.');
            $this->redirect("/story/{$storyId}");
            return;
        }

        $this->session()->flash('success', 'Ваш комментарий успешно опубликован!');
        $this->redirect(comment_url($storyId, $commentId));
    }

    /**
     * Редактирование комментария (поддерживает AJAX и обычный POST)
     */
    public function edit(string $id): void
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
                $this->json(['success' => false, 'error' => $e->getMessage()], 400);
                return;
            }
            $this->session()->flash('error', $e->getMessage());
            $this->redirectBack();
            return;
        } 
        // 3. Ловим непредвиденные ошибки и логируем их
        catch (\Throwable $e) {
            $this->logError($e, 'Comments.edit');
            if ($isAjax) {
                $this->json(['success' => false, 'error' => 'Внутренняя ошибка сервера'], 500);
                return;
            }
            $this->session()->flash('error', 'Произошла непредвиденная ошибка.');
            $this->redirectBack();
            return;
        }

        if ($isAjax) {
            $html = markdown_comment($result['comment']['comment']);
            $this->json([
                'success' => true,
                'html' => $html,
                'raw' => $result['comment']['comment']
            ]);
            return;
        }

        $this->session()->flash('success', 'Комментарий успешно обновлён.');
        $this->redirect(comment_url((int)$result['comment']['story_id'], $commentId));
    }

    /**
     * Удаление комментария
     */
    public function delete(string $id): void
    {
        $commentId = (int)$id;
        $result = null;

        try {
            $result = $this->service(CommentService::class)->deleteComment($commentId);
        } catch (CommentPermissionException | \InvalidArgumentException $e) {
            $this->session()->flash('error', $e->getMessage());
            $this->redirectBack();
            return;
        } catch (\Throwable $e) {
            $this->logError($e, 'Comments.delete');
            $this->session()->flash('error', 'Произошла ошибка при удалении.');
            $this->redirectBack();
            return;
        }

        // Исключение уйдет сразу в Application::handleRedirect без логирования.
        $this->session()->flash('success', 'Комментарий успешно удален.');
        $this->redirect(comment_url($result['story_id'], $commentId));
    }

    /**
     * Восстановление комментария
     */
    public function restore(string $id): void
    {
        $commentId = (int)$id;
        $result = null;

        try {
            $result = $this->service(CommentService::class)->restoreComment($commentId);
        } catch (CommentValidationException | CommentPermissionException | \InvalidArgumentException $e) {
            $this->session()->flash('error', $e->getMessage());
            $this->redirectBack();
            return;
        } catch (\Throwable $e) {
            $this->logError($e, 'Comments.restore');
            $this->session()->flash('error', 'Произошла ошибка при восстановлении.');
            $this->redirectBack();
            return;
        }

        $this->session()->flash('success', 'Комментарий успешно восстановлен.');
        $this->redirect(comment_url($result['story_id'], $commentId));
    }

    /**
     * Комментарии конкретного пользователя
     */
    public function userComments(string $username): void
    {
        $userModel = $this->container->get(User::class);
        $user = $userModel->findByName($username);

        if (!$user) {
            throw new NotFoundException("Пользователь не найден");
        }

        $comments = $this->service(CommentService::class)->getUserComments((int)$user['id'], 50);
        $userContext = $this->getUserContext();

        $this->render('user_comments', [
            'profileUser' => $user,
            'comments' => $comments,
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'isModerator' => $userContext['isModerator'],
            'title' => 'Комментарии пользователя ' . e($username),
        ]);
    }

    /**
     * Хелпер для получения сессии (если его нет в базовом Controller)
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }
}