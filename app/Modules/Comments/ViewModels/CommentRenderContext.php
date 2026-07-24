<?php

declare(strict_types=1);

namespace App\Modules\Comments\ViewModels;

/**
 * Универсальный контекст для рендеринга любого комментария.
 * Используется и на странице истории, и в глобальной ленте, и в профиле пользователя.
 */
readonly class CommentRenderContext
{
    public function __construct(
        public int $currentUserId,
        public bool $isAdmin,
        public bool $isModerator,
        public bool $canDownvote,
        
        public ?int $lastReadCommentId = null,
        public ?array $commentsTree = null,
        public ?\Closure $renderTree = null,
    ) {}
}