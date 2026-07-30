<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

class PasswordResetToken extends Model
{
    protected string $table = 'password_resets';

    protected array $fillable = [
        'email',
        'token'
    ];

    /**
     * Создать новый токен для email.
     */
    public function createToken(string $email, string $token): bool
    {
        $this->deleteByEmail($email);

        return $this->db->execute(
            "INSERT INTO `password_resets` (`email`, `token`, `created_at`) 
             VALUES (:email, :token, NOW())",
            [
                'email' => $email,
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
            "SELECT * FROM `password_resets` WHERE `token` = :token LIMIT 1",
            ['token' => $token]
        );
    }

    /**
     * Удалить токен после использования.
     */
    public function deleteByToken(string $token): bool
    {
        return $this->db->execute(
            "DELETE FROM `password_resets` WHERE `token` = :token",
            ['token' => $token]
        ) > 0;
    }

    /**
     * Удалить все токены для email.
     */
    public function deleteByEmail(string $email): bool
    {
        return $this->db->execute(
            "DELETE FROM `password_resets` WHERE `email` = :email",
            ['email' => $email]
        ) > 0;
    }

    /**
     * Удалить просроченные токены (старше 1 часа).
     */
    public function cleanupExpired(): int
    {
        return $this->db->execute(
            "DELETE FROM `password_resets` 
             WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
    }
}