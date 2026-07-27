<?php

declare(strict_types=1);

namespace App\Modules\Stories\Events;

use W3a\Core\Events\Event;

class StoryRestored extends Event
{
    public function __construct(
        private int $storyId,
        private int $restoredByUserId,
        private string $reason = 'История восстановлена из архива'
    ) {}

    public function getName(): string
    {
        return 'moderation.story_restored';
    }

    public function getCategory(): string
    {
        return 'moderation';
    }

    public function getData(): array
    {
        return [
            'story_id' => $this->storyId,
            'restored_by' => $this->restoredByUserId,
            'reason' => $this->reason,
            'description' => sprintf(
                'Модератор (ID: %d) восстановил историю ID: %d. Причина: %s',
                $this->restoredByUserId,
                $this->storyId,
                $this->reason
            ),
        ];
    }
}