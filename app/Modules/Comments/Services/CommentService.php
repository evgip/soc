<?php

declare(strict_types=1);

namespace App\Modules\Comments\Services;

use App\Modules\Comments\Models\Comment;
use App\Modules\Notifications\Services\NotificationService;
use W3a\Core\Support\Validator;
use W3a\Core\Events\EventDispatcher;
use W3a\Core\Security\UserContext;

use App\Modules\Comments\Exceptions\CommentValidationException;
use App\Modules\Comments\Exceptions\CommentPermissionException;

use App\Modules\Comments\Events\CommentCreated;
use App\Modules\Comments\Events\CommentDeleted;
use App\Modules\Comments\Events\CommentRestored;
use App\Modules\Comments\Events\CommentUpdated;

/**
 * Сервис для работы с комментариями.
 */
class CommentService
{
    private Comment $commentModel;
    private NotificationService $notificationService;
    private EventDispatcher $eventDispatcher;
    private Validator $validator;
    private UserContext $currentUser;

    /**
     * Все 5 зависимостей строго обязательны.
     */
    public function __construct(
        Comment $commentModel,
        Validator $validator,
        NotificationService $notificationService,
        EventDispatcher $eventDispatcher,
        UserContext $currentUser
    ) {
        $this->commentModel = $commentModel;
        $this->validator = $validator;
        $this->notificationService = $notificationService;
        $this->eventDispatcher = $eventDispatcher;
        $this->currentUser = $currentUser;
    }

    /**
     * Создаёт новый комментарий.
     * 
     * @throws CommentValidationException Если текст не прошёл валидацию
     * @throws \RuntimeException Если не удалось сохранить в БД
     * @return int ID созданного комментария
     */
    public function createComment(int $storyId, string $text, ?int $parentId): int
    {
        // 1. Валидация текста
        if (!$this->validateCommentText($text)) {
            $minLength = config('constants.validation.comment_min_length', 2, 'int');
            throw new CommentValidationException(
                "Текст комментария должен содержать не менее {$minLength} символов."
            );
        }

        // 2. Создание комментария
        $commentData = [
            'story_id' => $storyId,
            'user_id' => $this->currentUser->id,
            'parent_id' => $parentId,
            'comment' => trim($text),
        ];

        $commentId = $this->commentModel->saveComment($commentData);

        // 3. Уведомления
        $this->notificationService->notifyCommentCreated($commentId);

        // 4. Диспатч события
        $this->eventDispatcher->dispatch(new CommentCreated(
            $commentId,
            $storyId,
            $this->currentUser->id,
            $parentId
        ));

        return $commentId;
    }

    /**
     * Обновляет текст комментария.
     * 
     * @throws \InvalidArgumentException Если комментарий не найден
     * @throws CommentPermissionException Если нет прав
     * @throws CommentValidationException Если текст не прошёл валидацию
     */
    public function updateComment(int $commentId, string $newText): array
    {
        $comment = $this->commentModel->withTrashed()->find($commentId);
        if (!$comment) {
            throw new \InvalidArgumentException("Комментарий не найден.");
        }

        if (!$this->canEditComment($comment)) {
            throw new CommentPermissionException('У вас нет прав для изменения этого комментария.');
        }

        if (!$this->validateCommentText($newText)) {
            throw new CommentValidationException('Текст комментария должен содержать не менее 2 символов.');
        }

        // Обновление
        $this->commentModel->update($commentId, ['comment' => trim($newText)]);
        $updatedComment = $this->commentModel->find($commentId);

        $this->eventDispatcher->dispatch(new CommentUpdated(
            $commentId,
            $this->currentUser->id,
            $this->checkIsAuthor($comment)
        ));

        return [
            'comment' => $updatedComment,
            'is_author' => $this->checkIsAuthor($comment),
        ];
    }

    /**
     * Мягко удаляет комментарий.
     * 
     * @throws \InvalidArgumentException Если комментарий не найден
     * @throws CommentPermissionException Если нет прав
     */
    public function deleteComment(int $commentId): array
    {
        $comment = $this->commentModel->find($commentId);
        if (!$comment) {
            throw new \InvalidArgumentException("Комментарий не найден.");
        }

        if (!$this->canDeleteComment($comment)) {
            throw new CommentPermissionException('Недостаточно прав для удаления.');
        }

        // Мягкое удаление
        $this->commentModel->softDeleteComment($commentId);

        // Диспатч события
        $this->eventDispatcher->dispatch(new CommentDeleted(
            $commentId,
            (int) $comment['story_id'],
            $this->currentUser->id,
            $this->checkIsAuthor($comment)
        ));

        return [
            'comment' => $comment,
            'story_id' => (int) $comment['story_id'],
            'is_author' => $this->checkIsAuthor($comment),
        ];
    }

    /**
     * Восстанавливает удалённый комментарий.
     * 
     * @throws \InvalidArgumentException Если комментарий не найден
     * @throws CommentValidationException Если комментарий не был удалён
     * @throws CommentPermissionException Если нет прав
     */
    public function restoreComment(int $commentId): array
    {
        $comment = $this->commentModel->withTrashed()->find($commentId);

        if (!$comment) {
            throw new \InvalidArgumentException("Комментарий не найден.");
        }

        if (empty($comment['deleted_at'])) {
            throw new CommentValidationException('Комментарий не был удалён.');
        }

        if (!$this->canDeleteComment($comment)) {
            throw new CommentPermissionException('Недостаточно прав для восстановления.');
        }

        $this->commentModel->restoreComment($commentId);

        $this->eventDispatcher->dispatch(new CommentRestored(
            $commentId,
            (int) $comment['story_id'],
            $this->currentUser->id,
            $this->checkIsAuthor($comment)
        ));

        return [
            'comment' => $comment,
            'story_id' => (int) $comment['story_id'],
            'is_author' => $this->checkIsAuthor($comment),
        ];
    }

    /**
     * Получает последние комментарии для глобальной ленты.
     */
    public function getLatestComments(int $limit = 50): array
    {
        return $this->commentModel->getLatestComments($limit);
    }

    /**
     * Получает комментарии конкретного пользователя.
     */
    public function getUserComments(int $userId, int $limit = 50): array
    {
        return $this->commentModel->getUserComments($userId, $limit);
    }

    // =========================================================================
    // ПРИВАТНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Проверяет, может ли текущий пользователь редактировать комментарий.
     */
    private function canEditComment(array $comment): bool
    {
        $isAuthor = (int) $comment['user_id'] === $this->currentUser->id;
        return $isAuthor || $this->currentUser->canModerate();
    }

    /**
     * Проверяет, может ли текущий пользователь удалять/восстанавливать комментарий.
     */
    private function canDeleteComment(array $comment): bool
    {
        return $this->canEditComment($comment);
    }

    /**
     * Валидирует текст комментария.
     */
    private function validateCommentText(string $text): bool
    {
        $minLength = config('constants.validation.comment_min_length', 2, 'int');
        return $this->validator->validate(
            ['comment_text' => $text], 
            ['comment_text' => "required|min:{$minLength}|max:2000"]
        );
    }

    /**
     * Определяет, является ли текущий пользователь автором комментария.
     */
    private function checkIsAuthor(array $comment): bool
    {
        return (int) $comment['user_id'] === $this->currentUser->id;
    }
}