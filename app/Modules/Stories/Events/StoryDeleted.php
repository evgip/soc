<?php

declare(strict_types=1);

namespace App\Modules\Stories\Events;

use W3a\Core\Events\Event;

class StoryDeleted extends Event
{
    public function __construct(
        private int $storyId,
        private int $deletedByUserId,
        private string $reason = 'История скрыта модератором'
    ) {}

    public function getName(): string
    {
        return 'moderation.story_deleted';
    }

    public function getCategory(): string
    {
        return 'moderation';
    }

    public function getData(): array
    {
        return [
            'story_id' => $this->storyId,
            'deleted_by' => $this->deletedByUserId,
            'reason' => $this->reason,
            'description' => sprintf(
                'Модератор (ID: %d) скрыл историю ID: %d. Причина: %s',
                $this->deletedByUserId,
                $this->storyId,
                $this->reason
            ),
        ];
    }
}