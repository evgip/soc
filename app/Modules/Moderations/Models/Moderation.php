<?php

namespace App\Modules\Moderations\Models;

use W3a\Core\Database\Model;

class Moderation extends Model
{
    protected string $table = 'audit_logs';

    protected array $fillable = [
        'moderator_id',
        'action',
        'target_type',
        'target_id',
        'reason',
        'user_id',
        'username',
        'role',
        'ip_address',
        'description',
        'category',
        'payload',
    ];

    // У moderations нет deleted_at — это неизменяемый лог
    protected bool $includeTrashed = true;

    /**
     * Переопределяем soft-delete constraint, т.к. в таблице нет deleted_at
     */
    protected function applySoftDeleteConstraint(string $sql): string
    {
        return $sql;
    }

    /**
     * Универсальное форматирование ссылки для лога модерации
     * 
     * @param string $verb       Глагол действия
     * @param string $targetType Тип объекта: 'comment', 'story', 'user'
     * @param int    $targetId   ID объекта
     * @param int    $storyId    ID связанной истории (для comment)
     * @return string HTML-строка для поля reason
     */
    public static function formatActionReason(
        string $verb,
        string $targetType,
        int $targetId,
        ?int $storyId = null
    ): string {
        return match ($targetType) {
            'comment' => $verb
                . ' <a href="/story/' . $storyId . '#comment-block-' . $targetId . '">комментарий #' . $targetId . '</a>'
                . ' в <a href="/story/' . $storyId . '">истории #' . $storyId . '</a>',

            'story'   => $verb
                . ' <a href="/story/' . $targetId . '">историю #' . $targetId . '</a>',

            'user'    => $verb
                . ' <a href="/user/' . $targetId . '">пользователя #' . $targetId . '</a>',

            default   => $verb . ' объект #' . $targetId,
        };
    }

    /**
     * Логирование действие модератора
     * 
     * ✅ ИЗМЕНЕНО: Метод теперь нестатический, использует $this
     */
    public function logCommentModeratorAction(
        string $verb,
        int $isAuthor,
        array $comment
    ): bool {
        if (!$isAuthor && \W3a\Core\Auth\Auth::isModerator()) {
            try {
                $history = self::formatActionReason($verb, 'comment', (int)$comment['id'], (int)$comment['story_id']);

                // ✅ Используем $this вместо new
                $this->create([
                    'user_id'     => (int) $_SESSION['user_id'],
                    'username'    => $_SESSION['user_name'] ?? 'Unknown',
                    'role'        => $_SESSION['user_role'] ?? 'moderator',
                    'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    'action'      => 'edit_comment',
                    'description' => $history,
                    'category'    => 'moderation',
                    'payload'     => json_encode([
                        'comment_id' => (int)$comment['id'],
                        'story_id'   => (int)$comment['story_id'],
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->error("Moderation log failed: " . $e->getMessage());
                }
            }
        }

        return true;
    }
    
    // ==========================================
    // МЕТОДЫ РАБОТЫ С БАНАМИ (user_bans)
    // ==========================================

    /**
     * Забанить пользователя.
     */
    public function banUser(int $userId, int $moderatorId, string $reason = '', ?string $expiresAt = null): bool
    {
        return $this->db->execute("
            INSERT INTO `user_bans` (`user_id`, `banned_by`, `reason`, `created_at`, `expires_at`)
            VALUES (:user_id, :mod_id, :reason, NOW(), :expires_at)
        ", [
            'user_id'    => $userId,
            'mod_id'     => $moderatorId,
            'reason'     => $reason,
            'expires_at' => $expiresAt,
        ]) > 0;
    }

    /**
     * Досрочно разбанить пользователя.
     */
    public function unbanUser(int $userId, ?int $unbannedBy = null): bool
    {
        return $this->db->execute("
            UPDATE `user_bans`
            SET `unbanned_at` = NOW(),
                `unbanned_by` = :unbanned_by
            WHERE `user_id` = :user_id
              AND `unbanned_at` IS NULL
              AND (`expires_at` IS NULL OR `expires_at` > NOW())
        ", [
            'user_id'      => $userId,
            'unbanned_by'  => $unbannedBy,
        ]) > 0;
    }

    /**
     * Проверить, забанен ли пользователь прямо сейчас.
     */
    public function isUserBanned(int $userId): bool
    {
        $count = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM `user_bans`
            WHERE `user_id` = :user_id
              AND `unbanned_at` IS NULL
              AND (`expires_at` IS NULL OR `expires_at` > NOW())
        ", ['user_id' => $userId]);

        return $count > 0;
    }

    /**
     * Получить информацию об активном бане пользователя.
     */
    public function getActiveBan(int $userId): ?array
    {
        return $this->db->fetchOne("
            SELECT b.*, 
                   u.username AS banned_by_name
            FROM `user_bans` b
            LEFT JOIN `users` u ON u.id = b.banned_by
            WHERE b.`user_id` = :user_id
              AND b.`unbanned_at` IS NULL
              AND (b.`expires_at` IS NULL OR b.`expires_at` > NOW())
            ORDER BY b.`created_at` DESC
            LIMIT 1
        ", ['user_id' => $userId]);
    }

    /**
     * Получить полную историю банов пользователя.
     */
    public function getBanHistory(int $userId): array
    {
        return $this->db->fetchAll("
            SELECT b.*, 
                   u1.username AS banned_by_name,
                   u2.username AS unbanned_by_name
            FROM `user_bans` b
            LEFT JOIN `users` u1 ON u1.id = b.banned_by
            LEFT JOIN `users` u2 ON u2.id = b.unbanned_by
            WHERE b.`user_id` = :user_id
            ORDER BY b.`created_at` DESC
        ", ['user_id' => $userId]);
    }

    /**
     * Получить список всех активных банов (для админ-панели).
     */
    public function getActiveBans(int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare("
            SELECT b.*, 
                   u1.username AS user_name,
                   u2.username AS banned_by_name
            FROM `user_bans` b
            JOIN `users` u1 ON u1.id = b.user_id
            LEFT JOIN `users` u2 ON u2.id = b.banned_by
            WHERE b.`unbanned_at` IS NULL
              AND (b.`expires_at` IS NULL OR b.`expires_at` > NOW())
            ORDER BY b.`created_at` DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $total = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM `user_bans`
            WHERE `unbanned_at` IS NULL
              AND (`expires_at` IS NULL OR `expires_at` > NOW())
        ");

        return [
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil($total / $perPage),
            'current_page' => $page,
        ];
    }

    /**
     * Автоматически снять истёкшие баны (для cron-задачи).
     */
    public function expireOldBans(): int
    {
        $stmt = $this->db->prepare("
            UPDATE `user_bans`
            SET `unbanned_at` = NOW(),
                `unbanned_by` = NULL
            WHERE `unbanned_at` IS NULL
              AND `expires_at` IS NOT NULL
              AND `expires_at` <= NOW()
        ");
        $stmt->execute();

        return $stmt->rowCount();
    }
}
