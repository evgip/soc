<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

class EmailActivation extends Model
{
    protected string $table = 'email_activations';

    protected array $fillable = [
        'user_id',
        'token'
    ];

    private const TOKEN_LIFETIME = 86400;

    /**
     * Создать новый токен активации для пользователя.
     */
    public function createToken(int $userId, string $token): bool
    {
        $this->deleteByUserId($userId);

        return $this->db->execute(
            "INSERT INTO `email_activations` (`user_id`, `token`, `created_at`) 
             VALUES (:user_id, :token, NOW())",
            [
                'user_id' => $userId,
                'token' => $token
            ]
        ) > 0;
    }

    /**
     * Найти токен в базе данных.
     */
    public function findByToken(string $token): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `email_activations` WHERE `token` = :token LIMIT 1",
            ['token' => $token]
        );
    }

    /**
     * Удалить токен после использования.
     */
    public function deleteByToken(string $token): bool
    {
        return $this->db->execute(
            "DELETE FROM `email_activations` WHERE `token` = :token",
            ['token' => $token]
        ) > 0;
    }

    /**
     * Удалить все токены для пользователя.
     */
    public function deleteByUserId(int $userId): bool
    {
        return $this->db->execute(
            "DELETE FROM `email_activations` WHERE `user_id` = :user_id",
            ['user_id' => $userId]
        ) > 0;
    }

    /**
     * Удалить просроченные токены (старше 24 часов).
     */
    public function cleanupExpired(): int
    {
        return $this->db->execute(
            "DELETE FROM `email_activations` 
             WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
    }
}