<?php

declare(strict_types=1);

namespace App\Modules\Muted\Models;

use W3a\Core\Model;

class MutedUser extends Model
{
    protected string $table = 'muted_users';

    protected array $fillable = ['user_id', 'muted_user_id'];

    /**
     * Игнорировать пользователя
     */
    public function mute(int $userId, int $mutedUserId): bool
    {
        if ($userId === $mutedUserId) {
            return false;
        }

        try {
            $sql = "INSERT INTO `muted_users` (`user_id`, `muted_user_id`) 
                    VALUES (:user_id, :muted_user_id)
                    ON DUPLICATE KEY UPDATE `created_at` = CURRENT_TIMESTAMP";

            return $this->db->execute($sql, [
                'user_id' => $userId,
                'muted_user_id' => $mutedUserId,
            ]) > 0;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("MutedUser::mute failed: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Разблокировать пользователя
     */
    public function unmute(int $userId, int $mutedUserId): bool
    {
        return $this->db->execute(
            "DELETE FROM `muted_users` WHERE `user_id` = ? AND `muted_user_id` = ?",
            [$userId, $mutedUserId]
        ) > 0;
    }

    /**
     * Проверить, игнорирован ли пользователь
     */
    public function isMuted(int $userId, int $mutedUserId): bool
    {
        return (bool)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `muted_users` WHERE `user_id` = ? AND `muted_user_id` = ?",
            [$userId, $mutedUserId]
        );
    }

    /**
     * Получить список игнорируемых пользователей с данными
     */
    public function getMutedList(int $userId): array
    {
        $sql = "SELECT u.id, u.username, u.created_at, up.avatar, mu.created_at as muted_at
                FROM `muted_users` mu
                JOIN `users` u ON mu.muted_user_id = u.id
                LEFT JOIN `user_profiles` up ON u.id = up.user_id
                WHERE mu.user_id = :user_id
                ORDER BY mu.created_at DESC";

        return $this->db->fetchAll($sql, ['user_id' => $userId]);
    }

    /**
     * Получить массив ID игнорируемых пользователей (для фильтрации)
     */
    public function getMutedUserIds(int $userId): array
    {
        $result = $this->db->fetchAll(
            "SELECT `muted_user_id` FROM `muted_users` WHERE `user_id` = ?",
            [$userId]
        );
        return array_column($result, 'muted_user_id');
    }
}
