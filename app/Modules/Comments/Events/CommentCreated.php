<?php

declare(strict_types=1);

namespace App\Modules\Comments\Events;

use W3a\Core\Events\Event;

/**
 * Событие создания нового комментария.
 *
 * Генерируется после успешного сохранения комментария в базе данных.
 * Используется слушателями для:
 *  - Увеличения счётчика комментариев у истории (UpdateStoryCommentsCountListener)
 *  - Отправки уведомлений автору истории (NotificationListener)
 *  - Логирования действий пользователя (AuditListener)
 */
class CommentCreated extends Event
{
    /**
     * @param int      $commentId Уникальный идентификатор созданного комментария
     * @param int      $storyId   Идентификатор истории, к которой оставлен комментарий
     * @param int      $userId    Идентификатор автора комментария
     * @param int|null $parentId  Идентификатор родительского комментария (null, если это корневой комментарий)
     */
    public function __construct(
        private int $commentId,
        private int $storyId,
        private int $userId,
        private ?int $parentId = null
    ) {
    }

    /**
     * Получить идентификатор созданного комментария.
     *
     * @return int
     */
    public function getCommentId(): int
    {
        return $this->commentId;
    }

    /**
     * Получить идентификатор истории.
     *
     * @return int
     */
    public function getStoryId(): int
    {
        return $this->storyId;
    }

    /**
     * Получить идентификатор автора комментария.
     *
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Получить идентификатор родительского комментария.
     *
     * @return int|null
     */
    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    /**
     * Получить строковое имя события.
     *
     * Используется как значение поля `action` в таблице `audit_logs`.
     * Формат: 'moderation.comment_created' (по аналогии с StoryDeleted).
     *
     * @return string
     */
    public function getName(): string
    {
        return 'moderation.comment_created';
    }

    /**
     * Получить категорию события для фильтрации в журнале.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return 'moderation';
    }

    /**
     * Получить массив данных события (для логирования и аудита).
     *
     * Содержит все данные о событии, включая человекочитаемое описание,
     * которое будет записано в поле `description` таблицы `audit_logs`.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [
            'comment_id'  => $this->commentId,
            'story_id'    => $this->storyId,
            'user_id'     => $this->userId,
            'parent_id'   => $this->parentId,
            'description' => sprintf(
                'Пользователь (ID: %d) добавил новый комментарий ID: %d в истории ID: %d%s',
                $this->userId,
                $this->commentId,
                $this->storyId,
                $this->parentId !== null ? " (ответ на комментарий ID: {$this->parentId})" : ''
            ),
        ];
    }
}