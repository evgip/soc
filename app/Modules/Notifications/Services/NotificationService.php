<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\Notification;
use App\Modules\Comments\Models\Comment;
use App\Modules\Users\Models\User;
use App\Modules\Muted\Services\MuteService;
use W3a\Core\Logger;

class NotificationService
{
    private Notification $notificationModel;
    private Comment $commentModel;
    private User $userModel;
    private Logger $logger;
    private ?MuteService $muteService;

    private const ALLOWED_TYPES = ['reply', 'mention', 'message'];
    private const DEFAULT_PER_PAGE = 25;


    public function __construct(
        Notification $notificationModel,
        Comment $commentModel,
        User $userModel,
        Logger $logger,
        ?MuteService $muteService = null
    ) {
        $this->notificationModel = $notificationModel;
        $this->commentModel = $commentModel;
        $this->userModel = $userModel;
        $this->logger = $logger;
        $this->muteService = $muteService;
    }

    // =========================================================================
    // МЕТОДЫ ДЛЯ КОНТРОЛЛЕРА
    // =========================================================================

    public function getNotificationsForIndex(
        int $userId,
        string $type = 'all',
        int $page = 1,
        int $limit = self::DEFAULT_PER_PAGE
    ): array {
        $normalizedType = $this->normalizeType($type);

        $mutedUserIds = $this->getMutedUserIds($userId);

        $notifications = $this->notificationModel->getUserNotifications(
            $userId,
            $normalizedType,
            $limit,
            max(1, $page),
            $mutedUserIds
        );

        return [
            'notifications' => $notifications,
            'currentType' => $normalizedType ?? 'all',
            'counts' => $this->getUnreadCountsByType($userId, $mutedUserIds),
            'totalUnread' => $this->getUnreadCount($userId, $mutedUserIds),
            'allowedTypes' => self::ALLOWED_TYPES,
        ];
    }

    public function getUserNotifications(
        int $userId,
        ?string $type = null,
        int $limit = self::DEFAULT_PER_PAGE,
        int $page = 1
    ): array {
        if ($userId <= 0) {
            return [];
        }

        $normalizedType = $this->normalizeType($type);
        $limit = max(1, min($limit, 100));
        $page = max(1, $page);

        $mutedUserIds = $this->getMutedUserIds($userId);

        return $this->notificationModel->getUserNotifications(
            $userId,
            $normalizedType,
            $limit,
            $page,
            $mutedUserIds
        );
    }

    public function getUnreadCountsByType(int $userId, array $mutedUserIds = []): array
    {
        if ($userId <= 0) {
            return ['reply' => 0, 'mention' => 0, 'message' => 0];
        }

        $unreadCounts = $this->notificationModel->getUnreadCountByType($userId, $mutedUserIds);

        $counts = ['reply' => 0, 'mention' => 0, 'message' => 0];

        if (is_array($unreadCounts)) {
            foreach ($unreadCounts as $row) {
                $type = $row['type'] ?? null;
                if ($type && isset($counts[$type])) {
                    $counts[$type] = (int)$row['count'];
                }
            }
        }

        return $counts;
    }

    public function getUnreadCount(int $userId, array $mutedUserIds = []): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return (int)$this->notificationModel->getUnreadCount($userId, $mutedUserIds);
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return false;
        }

        return $this->notificationModel->markAsRead($notificationId, $userId);
    }

    public function markAllAsRead(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return $this->notificationModel->markAllAsRead($userId);
    }

    // =========================================================================
    // БИЗНЕС-ЛОГИКА СОЗДАНИЯ УВЕДОМЛЕНИЙ
    // =========================================================================

    public function notifyCommentCreated(int $commentId): void
    {
        if ($commentId <= 0) {
            return;
        }

        try {
            $comment = $this->commentModel->getWithStoryInfo($commentId);

            if (!$comment) {
                return;
            }

            $authorId = (int)($comment['author_id'] ?? 0);
            $storyAuthorId = (int)($comment['story_author_id'] ?? 0);
            $parentCommentId = !empty($comment['parent_id']) ? (int)$comment['parent_id'] : null;

            if ($authorId <= 0) {
                return;
            }

            $notifiedUserIds = [$authorId];

            if ($parentCommentId) {
                $this->notifyParentCommentAuthor(
                    $parentCommentId,
                    $authorId,
                    (int)$comment['id'],
                    $notifiedUserIds
                );
            }

            if ($storyAuthorId > 0) {
                $this->notifyStoryAuthor(
                    $storyAuthorId,
                    $authorId,
                    (int)$comment['id'],
                    (bool)($comment['user_is_following'] ?? false),
                    $notifiedUserIds
                );
            } 

            $commentText = (string)($comment['comment'] ?? '');
            if (!empty($commentText)) {
                $this->processMentions(
                    $commentText,
                    (int)$comment['id'],
                    $authorId,
                    $notifiedUserIds
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error("[NOTIFICATIONS] Error in notifyCommentCreated: " . $e->getMessage());
        }
    }

    public function notifyMessageSent(int $messageId, int $recipientId, int $senderId): void
    {
        if ($messageId <= 0 || $recipientId <= 0 || $senderId <= 0) {
            return;
        }

        if ($recipientId === $senderId) {
            return;
        }

        // Не создаём уведомление, если отправитель замьючен получателем
        if ($this->isMutedBy($senderId, $recipientId)) {
            return;
        }

        if (!$this->notificationModel->userWantsNotification($recipientId, 'notify_on_message')) {
            return;
        }

        try {
            $this->notificationModel->createMessageNotification(
                $recipientId,
                $messageId,
                $senderId,
                'Отправил вам личное сообщение'
            );
        } catch (\Throwable $e) {
            $this->logger->error("[NOTIFICATIONS] Error in notifyMessageSent: " . $e->getMessage());
        }
    }

    // =========================================================================
    // ПРИВАТНЫЕ ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Получить ID замьюченных пользователей
     */
    private function getMutedUserIds(int $userId): array
    {
        if ($userId <= 0 || $this->muteService === null) {
            return [];
        }
        return $this->muteService->getMutedUserIds($userId);
    }

    /**
     * Проверить, замьючен ли $actorId пользователем $byUserId
     */
    private function isMutedBy(int $actorId, int $byUserId): bool
    {
        if ($this->muteService === null) {
            return false;
        }
        return $this->muteService->isMuted($byUserId, $actorId);
    }

    private function normalizeType(?string $type): ?string
    {
        if ($type === null || $type === '' || $type === 'all') {
            return null;
        }

        $type = strtolower(trim($type));

        if (in_array($type, self::ALLOWED_TYPES, true)) {
            return $type;
        }

        return null;
    }

    private function notifyStoryAuthor(
        int $storyAuthorId,
        int $commentAuthorId,
        int $commentId,
        bool $isFollowing,
        array &$notifiedUserIds
    ): void {
        if ($storyAuthorId === $commentAuthorId) {
            return;
        }

        if (in_array($storyAuthorId, $notifiedUserIds, true)) {
            return;
        }

        if (!$isFollowing) {
            return;
        }

        // Не уведомляем, если автор комментария замьючен автором истории
        if ($this->isMutedBy($commentAuthorId, $storyAuthorId)) {
            return;
        }

        if (!$this->notificationModel->userWantsNotification($storyAuthorId, 'notify_on_story_comment')) {
            return;
        }

        try {
            $this->notificationModel->createStoryCommentNotification(
                $storyAuthorId,
                $commentId,
                $commentAuthorId,
                'Прокомментировал вашу публикацию'
            );
            $notifiedUserIds[] = $storyAuthorId;
        } catch (\Throwable $e) {
            $this->logger->error("[NOTIFICATIONS] Error notifying story author: " . $e->getMessage());
        }
    }

    private function notifyParentCommentAuthor(
        int $parentCommentId,
        int $commentAuthorId,
        int $commentId,
        array &$notifiedUserIds
    ): void {
        try {
            $parentAuthorId = $this->commentModel->getAuthorId($parentCommentId);
        } catch (\Throwable $e) {
            $this->logger->error("[NOTIFICATIONS] Error getting parent author: " . $e->getMessage());
            return;
        }

        if ($parentAuthorId === null || $parentAuthorId <= 0) {
            return;
        }

        if ($parentAuthorId === $commentAuthorId) {
            return;
        }

        if (in_array($parentAuthorId, $notifiedUserIds, true)) {
            return;
        }

        // Не уведомляем, если автор ответа замьючен автором родительского комментария
        if ($this->isMutedBy($commentAuthorId, $parentAuthorId)) {
            return;
        }

        if (!$this->notificationModel->userWantsNotification($parentAuthorId, 'notify_on_reply')) {
            return;
        }

        try {
            $this->notificationModel->createReplyNotification(
                $parentAuthorId,
                $commentId,
                $commentAuthorId,
                'Ответил на ваш комментарий'
            );
            $notifiedUserIds[] = $parentAuthorId;
        } catch (\Throwable $e) {
            $this->logger->error("[NOTIFICATIONS] Error notifying parent author: " . $e->getMessage());
        }
    }

    private function processMentions(
        string $text,
        int $commentId,
        int $authorId,
        array &$notifiedUserIds
    ): void {
        if (!preg_match_all('/@[a-zA-Z0-9_]+/', $text, $matches)) {
            return;
        }

        if (empty($matches[0])) {
            return;
        }

        $usernames = array_unique($matches[0]);

        foreach ($usernames as $usernameWithAt) {
            $username = ltrim($usernameWithAt, '@');

            if (empty($username)) {
                continue;
            }

            try {
                $user = $this->userModel->findByName($username);

                if (!$user) {
                    continue;
                }

                $userId = (int)($user['id'] ?? 0);

                if ($userId <= 0 || $userId === $authorId || in_array($userId, $notifiedUserIds, true)) {
                    continue;
                }

                // Не уведомляем, если упомянувший замьючен пользователем
                if ($this->isMutedBy($authorId, $userId)) {
                    continue;
                }

                if (!$this->notificationModel->userWantsNotification($userId, 'notify_on_mention')) {
                    continue;
                }

                $this->notificationModel->createMentionNotification(
                    $userId,
                    $commentId,
                    $authorId,
                    'Упомянул вас в комментарии'
                );

                $notifiedUserIds[] = $userId;
            } catch (\Throwable $e) {
                $this->logger->error("[NOTIFICATIONS] Error processing mention @{$username}: " . $e->getMessage());
            }
        }
    }
}