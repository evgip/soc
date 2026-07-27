<?php

declare(strict_types=1);

namespace App\Modules\Comments\Events;

use W3a\Core\Events\Event;

/**
 * Событие восстановления удалённого комментария.
 *
 * Генерируется после успешного восстановления комментария в базе данных.
 * Используется слушателями для:
 *  - Увеличения счётчика комментариев у истории (UpdateStoryCommentsCountListener)
 *  - Логирования действий пользователя (AuditListener)
 */
class CommentRestored extends Event
{
    /**
     * @param int  $commentId Уникальный идентификатор восстановленного комментария
     * @param int  $storyId   Идентификатор истории, к которой принадлежит комментарий
     * @param int  $userId    Идентификатор пользователя, восстановившего комментарий
     * @param bool $isAuthor  Является ли восстанавливающий пользователь автором комментария
     */
    public function __construct(
        private int $commentId,
        private int $storyId,
        private int $userId,
        private bool $isAuthor = false
    ) {
    }

    /**
     * Получить идентификатор восстановленного комментария.
     */
    public function getCommentId(): int
    {
        return $this->commentId;
    }

    /**
     * Получить идентификатор истории.
     * Используется UpdateStoryCommentsCountListener для увеличения счётчика.
     */
    public function getStoryId(): int
    {
        return $this->storyId;
    }

    /**
     * Получить идентификатор пользователя, восстановившего комментарий.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Является ли восстанавливающий пользователь автором комментария.
     */
    public function isAuthor(): bool
    {
        return $this->isAuthor;
    }

    /**
     * Имя события для аудита.
     */
    public function getName(): string
    {
        return 'moderation.comment_restored';
    }

    /**
     * Категория события для фильтрации в журнале.
     */
    public function getCategory(): string
    {
        return 'moderation';
    }

    /**
     * Данные события для логирования.
     */
    public function getData(): array
    {
        return [
            'comment_id'  => $this->commentId,
            'story_id'    => $this->storyId,
            'user_id'     => $this->userId,
            'is_author'   => $this->isAuthor,
            'description' => sprintf(
                'Пользователь (ID: %d) восстановил комментарий ID: %d в истории ID: %d%s',
                $this->userId,
                $this->commentId,
                $this->storyId,
                $this->isAuthor ? ' (автор комментария)' : ''
            ),
        ];
    }
}