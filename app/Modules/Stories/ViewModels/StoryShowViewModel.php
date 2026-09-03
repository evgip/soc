<?php

declare(strict_types=1);

namespace App\Modules\Stories\ViewModels;

/**
 * ViewModel для страницы просмотра одной истории.
 * Инкапсулирует все данные и правила отображения, избавляя шаблон от бизнес-логики.
 */
readonly class StoryShowViewModel
{
    public function __construct(
        // Основные данные
        public array $story,
        public array $commentsTree,
        public array $currentCommentVotes,
        
        // Контекст пользователя
        public int $currentUserId,
        public bool $isAdmin,
        public bool $isModerator,
        public bool $isAuthor,
        
// Функциональность
		public bool $isStorySaved,
        
        // Предложения по изменению (Suggests)
        public int $userSuggestionsCount,
        public int $maxSuggestionsAllowed,
        public array $activeSuggestions,
        public array $changeLog,
        
        // Мета-данные и теги
        public array $allTags,
        public array $currentTagIds,
        public int $newCommentsCount,
        public int $lastReadCommentId,
        
        public readonly bool $canSeeFullContent = true,
        public readonly bool $hasFriendLinkAccess = false,
        
        public readonly array $storyCollections = [],
        public int $userClaps = 0,
    ) {}

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ДЛЯ ШАБЛОНА
    // =========================================================================

    /**
     * Можно ли показывать кнопку "Предложить правку"?
     * (Логика вынесена из шаблона сюда)
     */
    public function canShowSuggestButton(): bool
    {
        if ($this->currentUserId === 0) {
            return false;
        }

        // Модераторы и админы могут всегда, авторы своей истории — нет.
        // Обычные пользователи могут, если не превышен лимит.
        $hasPermission = $this->isModerator || $this->isAdmin || !$this->isAuthor;
        $hasQuota = $this->userSuggestionsCount < $this->maxSuggestionsAllowed;

        return $hasPermission && $hasQuota;
    }

    /**
     * Достиг ли пользователь лимита предложений?
     */
    public function hasReachedSuggestLimit(): bool
    {
        return !$this->isModerator && !$this->isAdmin && $this->userSuggestionsCount >= $this->maxSuggestionsAllowed;
    }

    /**
     * Показывать ли кнопку "Отметить прочитанным"?
     */
    public function canMarkAsRead(): bool
    {
        return $this->currentUserId > 0 && $this->newCommentsCount > 0;
    }
}
