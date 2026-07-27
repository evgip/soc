<?php

declare(strict_types=1);

namespace App\Modules\Stories\Events;

use W3a\Core\Events\Event;

/**
 * Событие создания новой истории.
 */
class StoryCreated extends Event
{
    /**
     * @param int $storyId ID созданной истории
     * @param int $userId ID автора
     * @param string $title Заголовок истории
     */
    public function __construct(
        private int $storyId,
        private int $userId,
        private string $title
    ) {}

    /**
     * Имя события для аудита.
     * Префикс 'story.' для единообразия с comment.*, moderation.*
     */
    public function getName(): string
    {
        return 'story.created';
    }

    /**
     * Категория события.
     * Создание истории — обычное действие пользователя.
     */
    public function getCategory(): string
    {
        return 'general';
    }

    /**
     * Данные события.
     */
    public function getData(): array
    {
        return [
            'story_id' => $this->storyId,
            'user_id' => $this->userId,
            'title' => $this->title,
            'description' => sprintf(
                'Пользователь (ID: %d) создал историю «%s» (ID: %d)',
                $this->userId,
                $this->title,
                $this->storyId
            ),
        ];
    }
}