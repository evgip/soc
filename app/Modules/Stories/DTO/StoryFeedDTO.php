<?php

declare(strict_types=1);

namespace App\Modules\Stories\DTO;

/**
 * DTO для передачи данных ленты статей в шаблон (Medium-стиль).
 */
class StoryFeedDTO
{
    public function __construct(
        public array $stories,
        public int $currentPage,
        public int $totalPages,
        public array $newCommentsMap,
        public string $sort,
        public ?string $author,
        public int $currentUserId,
        public bool $isAdmin,
        public array $currentVotes,
        public array $rssFeed,
        public string $pageTitle,
        public array $extraData = []
    ) {
    }
}