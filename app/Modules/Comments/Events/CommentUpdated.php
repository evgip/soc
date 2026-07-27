<?php

declare(strict_types=1);

namespace App\Modules\Comments\Events;

use W3a\Core\Events\Event;

/**
 * Событие обновления комментария.
 * 
 * Отправляется после успешного редактирования комментария.
 * Используется для аудита и логирования модерации.
 */
class CommentUpdated extends Event
{
    /**
     * @param int $commentId ID обновлённого комментария
     * @param int $userId ID пользователя, который редактировал
     * @param bool $isAuthor Является ли пользователь автором комментария
     */
    public function __construct(
        private int $commentId,
        private int $userId,
        private bool $isAuthor = true
    ) {}

    /**
     * Имя события для аудита.
     */
    public function getName(): string
    {
        return 'comment.updated';
    }

    /**
     * Категория события для фильтрации в журнале.
     * Если редактировал автор — обычное действие.
     * Если редактировал модератор — модерация.
     */
    public function getCategory(): string
    {
        return $this->isAuthor ? 'general' : 'moderation';
    }

    /**
     * Данные события.
     */
    public function getData(): array
    {
        return [
            'comment_id' => $this->commentId,
            'user_id' => $this->userId,
            'is_author' => $this->isAuthor,
            'description' => sprintf(
                'Пользователь (ID: %d) отредактировал %s',
                $this->userId,
                $this->isAuthor ? 'свой комментарий' : 'не свой комментарий'
            ),
        ];
    }
	
    /**
     * Получить идентификатор обновлённого комментария.
     */
    public function getCommentId(): int
    {
        return $this->commentId;
    }

    /**
     * Получить идентификатор пользователя, отредактировавшего комментарий.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Является ли редактирующий пользователь автором комментария.
     */
    public function isAuthor(): bool
    {
        return $this->isAuthor;
    }
}