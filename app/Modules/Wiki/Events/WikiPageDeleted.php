<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Events;

use W3a\Core\Events\Event;

/**
 * Событие удаления wiki страницы.
 *
 * Генерируется после успешного удаления (soft delete) wiki страницы.
 * Используется слушателями для:
 *  - Инвалидации кэша
 *  - Логирования действий пользователя
 *  - Уведомления администраторов/модераторов
 */
class WikiPageDeleted extends Event
{
    /**
     * @param int $pageId Уникальный идентификатор удалённой страницы
     * @param int $userId Идентификатор пользователя, удалившего страницу
     */
    public function __construct(
        private int $pageId,
        private int $userId
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
     * Получить идентификатор пользователя.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Имя события для аудита.
     */
    public function getName(): string
    {
        return 'wiki.deleted';
    }

    /**
     * Категория события.
     * Удаление wiki — модерационное действие.
     */
    public function getCategory(): string
    {
        return 'moderation';
    }

    /**
     * Данные события.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [
            'page_id'  => $this->pageId,
            'user_id'  => $this->userId,
            'description' => sprintf(
                'Пользователь (ID: %d) удалил wiki страницу (ID: %d)',
                $this->userId,
                $this->pageId
            ),
        ];
    }
}