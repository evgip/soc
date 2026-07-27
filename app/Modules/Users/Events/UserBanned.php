<?php

declare(strict_types=1);

namespace App\Modules\Users\Events;

use W3a\Core\Events\Event;

class UserBanned extends Event
{
    public function __construct(
        private int $userId,
        private int $bannedBy,
        private string $reason = 'Без указания причины'
    ) {}

    public function getName(): string
    {
        return 'moderation.user_banned';
    }

    public function getCategory(): string
    {
        return 'moderation';
    }

    public function getData(): array
    {
        return [
            'user_id'     => $this->userId,
            'banned_by'   => $this->bannedBy,
            'reason'      => $this->reason,
            'description' => sprintf(
                'Модератор (ID: %d) забанил пользователя ID: %d. Причина: %s',
                $this->bannedBy,
                $this->userId,
                $this->reason
            ),
        ];
    }
}