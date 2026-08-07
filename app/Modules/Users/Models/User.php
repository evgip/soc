<?php

namespace App\Modules\Users\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

class User extends Model
{
    protected string $table = 'users';

    // Разрешаем изменять только эти поля через массовое присваивание
    protected array $fillable = [
        'username',
        'email',
        'password',
        'role',
        'bio',
        'is_active'
    ];

    /**
     * Получить статистику активности пользователя
     */
    public function getProfileStats(int $userId): array
    {
        $storiesCount = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `stories` WHERE `user_id` = :uid AND `deleted_at` IS NULL",
            ['uid' => $userId]
        );

        $commentsCount = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `comments` WHERE `user_id` = :uid AND `deleted_at` IS NULL",
            ['uid' => $userId]
        );

        return [
            'stories_count'  => $storiesCount,
            'comments_count' => $commentsCount
        ];
    }
    
    /**
     * Вычислить суммарную карму пользователя
     */
    public function getUserKarma(int $userId): int
    {
        $sql = "SELECT SUM(total_score) as karma FROM (
                    SELECT SUM(`score`) as total_score FROM `stories` WHERE `user_id` = :uid1 AND `deleted_at` IS NULL
                    UNION ALL
                    SELECT SUM(`score`) as total_score FROM `comments` WHERE `user_id` = :uid2 AND `deleted_at` IS NULL
                ) as combined";

        return (int)($this->db->fetchColumn($sql, ['uid1' => $userId, 'uid2' => $userId]) ?? 0);
    }
    
    /**
     * Находит пользователя по имени (username).
     */
    public function findByName(string $username): ?array
    {
        $sql = "SELECT id, username FROM `{$this->table}` 
                WHERE username = :username AND deleted_at IS NULL";

        return $this->db->fetchOne($sql, ['username' => $username]);
    }

    /**
     * Находит username, role по id.
     */
    public function getUser(int $userId): ?array
    {
        return $this->db->fetchOne(
            "SELECT username, role FROM `users` WHERE `id` = :id LIMIT 1",
            ['id' => $userId]
        );
    }

    // ==========================================
    // Методы для работы с user_profiles
    // ==========================================
    
    public function getProfile(int $userId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `user_profiles` WHERE `user_id` = :id LIMIT 1",
            ['id' => $userId]
        );
    }

    public function updateProfile(int $userId, array $data): bool
    {
        $fields = [];
        $params = ['user_id' => $userId];
        $allowed = ['bio', 'avatar'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "`$key` = :$key";
                $params[$key] = $value;
            }
        }
        if (empty($fields)) return false;
        
        $exists = $this->getProfile($userId);
        if ($exists) {
            $sql = "UPDATE `user_profiles` SET " . implode(', ', $fields) . " WHERE `user_id` = :user_id";
        } else {
            $fields[] = "`user_id` = :user_id";
            $sql = "INSERT INTO `user_profiles` SET " . implode(', ', $fields);
        }
        
        return $this->db->execute($sql, $params) > 0;
    }

    // ==========================================
    // Методы для работы с user_settings
    // ==========================================
    
    public function getSettings(int $userId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `user_settings` WHERE `user_id` = :id LIMIT 1",
            ['id' => $userId]
        );
    }

    public function updateSettings(int $userId, array $data): bool
    {
        $fields = [];
        $params = ['user_id' => $userId];
        $allowed = ['notify_on_reply', 'notify_on_story_comment', 'notify_on_mention', 'notify_on_message', 'email_notifications'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "`$key` = :$key";
                $params[$key] = $value;
            }
        }
        if (empty($fields)) return false;
        
        $exists = $this->getSettings($userId);
        if ($exists) {
            $sql = "UPDATE `user_settings` SET " . implode(', ', $fields) . " WHERE `user_id` = :user_id";
        } else {
            $fields[] = "`user_id` = :user_id";
            $sql = "INSERT INTO `user_settings` SET " . implode(', ', $fields);
        }
        
        return $this->db->execute($sql, $params) > 0;
    }

    // ==========================================
    // Методы для работы с user_bans
    // ==========================================

    /**
     * Проверяет, забанен ли пользователь (активен ли бан).
     */
    public function isBanned(int $userId): bool
    {
        $sql = "SELECT id FROM `user_bans` 
                WHERE user_id = :id 
                  AND unbanned_at IS NULL 
                  AND (expires_at IS NULL OR expires_at > NOW())
                LIMIT 1";

        return (bool)$this->db->fetchOne($sql, ['id' => $userId]);
    }

    /**
     * Получить список всех пользователей с информацией о бане.
     * Использует LEFT JOIN с user_bans для вычисления is_banned.
     * 
     * @param bool $withTrashed Включать ли удалённых пользователей
     * @return array Массив пользователей с дополнительными полями:
     *               - is_banned (0|1)
     *               - ban_reason
     *               - banned_at
     *               - expires_at
     *               - banned_by
     */
    public function getAllUsersWithBanStatus(bool $withTrashed = false): array
    {
        $deletedCondition = $withTrashed ? '' : 'WHERE u.`deleted_at` IS NULL';
        
        $sql = "
            SELECT 
                u.*,
                -- Вычисляем is_banned: 1 если есть активный бан
                (
                    SELECT COUNT(*) 
                    FROM `user_bans` b 
                    WHERE b.`user_id` = u.id 
                      AND b.`unbanned_at` IS NULL 
                      AND (b.`expires_at` IS NULL OR b.`expires_at` > NOW())
                ) > 0 AS `is_banned`,
                -- Причина активного бана
                (
                    SELECT b.`reason` 
                    FROM `user_bans` b 
                    WHERE b.`user_id` = u.id 
                      AND b.`unbanned_at` IS NULL 
                      AND (b.`expires_at` IS NULL OR b.`expires_at` > NOW())
                    LIMIT 1
                ) AS `ban_reason`,
                -- Дата начала активного бана
                (
                    SELECT b.`created_at` 
                    FROM `user_bans` b 
                    WHERE b.`user_id` = u.id 
                      AND b.`unbanned_at` IS NULL 
                      AND (b.`expires_at` IS NULL OR b.`expires_at` > NOW())
                    LIMIT 1
                ) AS `banned_at`,
                -- Дата окончания активного бана (NULL = перманентный)
                (
                    SELECT b.`expires_at` 
                    FROM `user_bans` b 
                    WHERE b.`user_id` = u.id 
                      AND b.`unbanned_at` IS NULL 
                      AND (b.`expires_at` IS NULL OR b.`expires_at` > NOW())
                    LIMIT 1
                ) AS `expires_at`,
                -- ID модератора, который забанил
                (
                    SELECT b.`banned_by` 
                    FROM `user_bans` b 
                    WHERE b.`user_id` = u.id 
                      AND b.`unbanned_at` IS NULL 
                      AND (b.`expires_at` IS NULL OR b.`expires_at` > NOW())
                    LIMIT 1
                ) AS `banned_by`
            FROM `users` u
            {$deletedCondition}
            ORDER BY u.`created_at` DESC
        ";
        
        return $this->db->fetchAll($sql);
    }

    /**
     * Получает информацию об активном бане пользователя.
     */
    public function getBanInfo(int $userId): ?array
    {
        $sql = "SELECT * FROM `user_bans` 
                WHERE user_id = :id 
                  AND unbanned_at IS NULL 
                  AND (expires_at IS NULL OR expires_at > NOW())
                LIMIT 1";

        return $this->db->fetchOne($sql, ['id' => $userId]);
    }

    public function banUser(int $userId, ?int $bannedBy, ?string $reason, ?string $expiresAt = null): bool
    {
        $sql = "INSERT INTO `user_bans` (`user_id`, `banned_by`, `reason`, `created_at`, `expires_at`) 
                VALUES (:user_id, :banned_by, :reason, NOW(), :expires_at)";

        return $this->db->execute($sql, [
            'user_id' => $userId,
            'banned_by' => $bannedBy,
            'reason' => $reason,
            'expires_at' => $expiresAt
        ]) > 0;
    }

    public function unbanUser(int $userId, ?int $unbannedBy): bool
    {
        $sql = "UPDATE `user_bans` 
                SET `unbanned_at` = NOW(), `unbanned_by` = :unbanned_by 
                WHERE `user_id` = :user_id AND `unbanned_at` IS NULL";

        return $this->db->execute($sql, [
            'user_id' => $userId,
            'unbanned_by' => $unbannedBy
        ]) > 0;
    }
	
    public function updateLastReadComments(int $userId): void
    {
        $this->db->execute(
            "UPDATE users SET last_read_comments_at = NOW() WHERE id = :id",
            ['id' => $userId]
        );
    }

	// ==========================================
	// OAuth / Социальная авторизация
	// ==========================================

	/**
	 * Находит пользователя по email.
	 */
	public function findByEmail(string $email): ?array
	{
		return $this->db->fetchOne(
			"SELECT id, username, email, is_active FROM `{$this->table}` 
			 WHERE `email` = :email AND `deleted_at` IS NULL 
			 LIMIT 1",
			['email' => $email]
		);
	}

	/**
	 * Создаёт нового пользователя при OAuth-регистрации.
	 * 
	 * @param string $username Уникальное имя пользователя
	 * @param string|null $email Email из соц. сети (может быть null от VK)
	 * @param string $provider Провайдер (yandex, vk) — для генерации placeholder email
	 * @param string $providerUserId ID пользователя у провайдера
	 * @return int ID созданного пользователя
	 */
	public function createOAuthUser(string $username, ?string $email, string $provider, string $providerUserId): int
	{
		// Генерируем случайный пароль (пользователь будет входить через OAuth)
		$password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

		// 🆕 Если email не пришёл — генерируем уникальный placeholder
		// Это обходит ограничение NOT NULL + UNIQUE
		if (empty($email)) {
			$email = $provider . '_' . $providerUserId . '@social.local';
		}

		$this->db->execute(
			"INSERT INTO `{$this->table}` 
				(`username`, `email`, `password`, `role`, `is_active`, `created_at`)
			 VALUES (?, ?, ?, 'user', 1, NOW())",
			[$username, $email, $password]
		);

		return (int)$this->db->lastInsertId();
	}
}	