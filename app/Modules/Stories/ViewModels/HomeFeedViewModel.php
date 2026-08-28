<?php

declare(strict_types=1);

namespace App\Modules\Stories\ViewModels;

/**
 * ViewModel для главной страницы в стиле Medium.
 * Содержит 4 секции + основную ленту.
 */
readonly class HomeFeedViewModel
{
    public function __construct(
        // Основная лента (Hot/New/Top) — уже существующая
        public array $stories,
        public int $currentPage,
        public int $totalPages,
        public array $newCommentsMap,
        public string $sort,
        public int $currentUserId,
        public bool $isAdmin,
        public array $currentVotes,
        
        // Medium-секции
        public array $forYou,          // Персональные рекомендации
        public array $trending,        // Популярное за 24ч (5 штук)
        public array $staffPicks,      // Выбор редакции (3 штуки)
        
        // Контекст
        public bool $isLoggedIn,
        public bool $hasPersonalization, // Есть ли данные для персонализации
        public string $pageTitle,
        public array $rssFeed,
    ) {}

    /**
     * Показывать ли секцию "For You"?
     * Скрываем, если пользователь не залогинен ИЛИ секция пуста
     */
    public function shouldShowForYou(): bool
    {
        return $this->isLoggedIn && !empty($this->forYou);
    }

    /**
     * Показывать ли секцию "Trending"?
     */
    public function shouldShowTrending(): bool
    {
        return !empty($this->trending);
    }

    /**
     * Показывать ли секцию "Staff Picks"?
     */
    public function shouldShowStaffPicks(): bool
    {
        return !empty($this->staffPicks);
    }

    /**
     * Сколько секций будет показано (для адаптации дизайна)
     */
    public function visibleSectionsCount(): int
    {
        return (int)$this->shouldShowForYou() 
             + (int)$this->shouldShowTrending() 
             + (int)$this->shouldShowStaffPicks();
    }
}