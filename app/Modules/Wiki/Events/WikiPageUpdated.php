<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Events;

use W3a\Core\Events\Event;

/**
 * Событие обновления wiki страницы.
 *
 * Генерируется после успешного обновления wiki страницы.
 * Используется слушателями для:
 *  - Отправки уведомлений подписчикам страницы/тега
 *  - Инвалидации кэша
 *  - Логирования действий пользователя
 */
class WikiPageUpdated extends Event
{
    /**
     * @param int         $pageId         Уникальный идентификатор обновлённой страницы
     * @param int         $userId         Идентификатор автора изменений
     * @param int         $revisionNumber Номер новой ревизии
     * @param string|null $editSummary    Описание изменений
     */
    public function __construct(
        private int $pageId,
        private int $userId,
        private int $revisionNumber,
        private ?string $editSummary = null
    ) {
    }

    /**
     * Получить идентификатор страницы.
     */
    public function getPageId(): int
    {
        return $this->pageId;
    }

    /**
     * Получить идентификатор автора изменений.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Получить номер новой ревизии.
     */
    public function getRevisionNumber(): int
    {
        return $this->revisionNumber;
    }

    /**
     * Получить описание изменений.
     */
    public function getEditSummary(): ?string
    {
        return $this->editSummary;
    }

    /**
     * Имя события для аудита.
     */
    public function getName(): string
    {
        return 'wiki.updated';
    }

    /**
     * Категория события.
     */
    public function getCategory(): string
    {
        return 'general';
    }

    /**
     * Данные события.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [
            'page_id'         => $this->pageId,
            'user_id'         => $this->userId,
            'revision_number' => $this->revisionNumber,
            'edit_summary'    => $this->editSummary,
            'description'     => sprintf(
                'Пользователь (ID: %d) обновил wiki страницу (ID: %d), ревизия #%d%s',
                $this->userId,
                $this->pageId,
                $this->revisionNumber,
                $this->editSummary ? ": {$this->editSummary}" : ''
            ),
        ];
    }
}