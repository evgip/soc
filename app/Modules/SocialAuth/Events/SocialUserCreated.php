<?php

declare(strict_types=1);

namespace App\Modules\SocialAuth\Events;

use W3a\Core\Events\Event;

class SocialUserCreated extends Event
{
    public function __construct(
        public readonly int $userId,
        public readonly string $provider
    ) {}
}