<?php

declare(strict_types=1);

namespace App\Modules\Suggestions\Events;

use W3a\Core\Events\Event;

/**
 * Событие обновления контента через систему предложений.
 * 
 * Отправляется после применения изменений к статье или комментарию.
 * Используется для аудита и логирования модерации.
 * 
 * Триггеры:
 * - Достигнут кворум сообщества (QUORUM_SIZE)
 * - Модератор одобрил предложение вручную
 */
class ContentUpdated extends Event
{
    /**
     * @param string $targetType Тип контента ('Story' или 'Comment')
     * @param int    $targetId   ID контента
     * @param array  $oldValues  Старые значения изменённых полей
     * @param array  $newValues  Новые значения изменённых полей
     * @param string $source     Источник изменений ('community_quorum' или 'moderator')
     */
    public function __construct(
        private string $targetType,
        private int $targetId,
        private array $oldValues,
        private array $newValues,
        private string $source
    ) {}

    /**
     * Имя события для аудита.
     */
    public function getName(): string
    {
        return 'content.updated';
    }

    /**
     * Категория события для фильтрации в журнале.
     * 
     * Если изменения применил кворум сообщества — обычное действие.
     * Если изменения применил модератор — модерация.
     */
    public function getCategory(): string
    {
        return $this->source === 'moderator' ? 'moderation' : 'general';
    }

    /**
     * Данные события.
     */
    public function getData(): array
    {
        return [
            'target_type' => $this->targetType,
            'target_id'   => $this->targetId,
            'old_values'  => $this->oldValues,
            'new_values'  => $this->newValues,
            'source'      => $this->source,
            'description' => sprintf(
                'Контент %s #%d был обновлён через %s',
                $this->targetType,
                $this->targetId,
                $this->source === 'moderator' ? 'модератора' : 'кворум сообщества'
            ),
        ];
    }
}