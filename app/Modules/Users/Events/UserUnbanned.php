<?php

declare(strict_types=1);

namespace App\Modules\Users\Events;

use W3a\Core\Events\Event;

class UserUnbanned extends Event
{
    public function __construct(
        private int $userId,
        private int $unbannedBy,
        private string $reason = 'Разбан пользователя'
    ) {}

    public function getName(): string
    {
        return 'moderation.user_unbanned';
    }

    public function getCategory(): string
    {
        return 'moderation';
    }

    public function getData(): array
    {
        return [
            'user_id'     => $this->userId,
            'unbanned_by' => $this->unbannedBy,
            'reason'      => $this->reason,
            'description' => sprintf(
                'Модератор (ID: %d) разбанил пользователя ID: %d',
                $this->unbannedBy,
                $this->userId
            ),
        ];
    }
}