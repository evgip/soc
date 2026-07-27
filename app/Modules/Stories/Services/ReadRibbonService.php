<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use App\Modules\Stories\Models\ReadRibbon;
use W3a\Core\Security\UserContext;

/**
 * Сервис для управления статусом прочтения историй и комментариев.
 */
class ReadRibbonService
{
    private ReadRibbon $readRibbon;
    private UserContext $currentUser;

    public function __construct(ReadRibbon $readRibbon, UserContext $currentUser)
    {
        $this->readRibbon = $readRibbon;
        $this->currentUser = $currentUser;
    }

    /**
     * Обрабатывает факт просмотра истории и возвращает количество новых комментариев.
     * Синхронизирует ленту прочтения, если обнаружен рассинхрон.
     */
    public function handleStoryView(int $storyId): int
    {
        if ($this->currentUser->isGuest()) {
            return 0;
        }

        $userId = $this->currentUser->id;
        $newCount = $this->readRibbon->getNewCommentsCount($userId, $storyId);

        if ($newCount > 0) {
            $realNewCount = $this->readRibbon->countRealNewComments($storyId, $userId);

            if ($realNewCount === 0) {
                $this->readRibbon->syncForUserAndStory($userId, $storyId);
                $newCount = 0;
            }
        }

        if ($newCount > 0) {
            $this->readRibbon->syncForUserAndStory($userId, $storyId);
        }

        return $newCount;
    }

    /**
     * Принудительно отмечает историю как прочитанную.
     */
    public function markAsRead(int $storyId): void
    {
        if ($this->currentUser->isGuest()) {
            return;
        }

        $this->readRibbon->syncForUserAndStory($this->currentUser->id, $storyId);
    }

    /**
     * Массовая отметка историй как прочитанных.
     */
    public function markStoriesAsRead(array $storyIds): void
    {
        if (empty($storyIds) || $this->currentUser->isGuest()) {
            return;
        }

        $this->readRibbon->syncForStories($this->currentUser->id, $storyIds);
    }
}