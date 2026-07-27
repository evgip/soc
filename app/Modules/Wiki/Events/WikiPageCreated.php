<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Events;

use W3a\Core\Events\Event;

/**
 * Событие создания новой wiki страницы.
 *
 * Генерируется после успешного сохранения wiki страницы в базе данных.
 * Используется слушателями для:
 *  - Отправки уведомлений подписчикам тега (NotificationListener)
 *  - Обновления кэша wiki страниц тега
 *  - Логирования действий пользователя (AuditListener)
 */
class WikiPageCreated extends Event
{
    /**
     * @param int      $pageId  Уникальный идентификатор созданной страницы
     * @param int      $userId  Идентификатор автора страницы
     * @param int|null $tagId   Идентификатор тега, к которому привязана страница
     * @param string   $title   Заголовок wiki страницы
     */
    public function __construct(
        private int $pageId,
        private int $userId,
        private ?int $tagId = null,
        private string $title = ''
    ) {
    }

    /**
     * Получить идентификатор созданной страницы.
     */
    public function getPageId(): int
    {
        return $this->pageId;
    }

    /**
     * Получить идентификатор автора страницы.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Получить идентификатор тега.
     */
    public function getTagId(): ?int
    {
        return $this->tagId;
    }

    /**
     * Получить заголовок страницы.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Получить строковое имя события.
     *
     * Используется как значение поля `action` в таблице `audit_logs`.
     * Префикс 'wiki.' для единообразия с story.*, comment.*
     */
    public function getName(): string
    {
        return 'wiki.created';
    }

    /**
     * Получить категорию события для фильтрации в журнале.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return 'general';
    }

    /**
     * Получить массив данных события (для логирования и аудита).
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [
            'page_id'  => $this->pageId,
            'user_id'  => $this->userId,
            'tag_id'   => $this->tagId,
            'title'    => $this->title,
            'description' => sprintf(
                'Пользователь (ID: %d) создал wiki страницу «%s» (ID: %d) для тега ID: %s',
                $this->userId,
                $this->title,
                $this->pageId,
                $this->tagId !== null ? (string)$this->tagId : '—'
            ),
        ];
    }
}