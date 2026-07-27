<?php

namespace App\Modules\Auth\Models;

use W3a\Core\Model;
use W3a\Core\Database;
use W3a\Core\Logger;

class RememberToken extends Model
{
    protected string $table = 'remember_tokens';

    /**
     * Создать новый remember token для пользователя
     */
    public function createToken(
        int $userId,
        int $days = 30,
        ?string $userAgent = null,
        ?string $ipAddress = null
    ): array {
        $selector = bin2hex(random_bytes(6));
        $validator = bin2hex(random_bytes(32));
        $hashedValidator = hash('sha256', $validator);
        $token = $selector . ':' . $validator;
        $expiresAt = date('Y-m-d H:i:s', time() + ($days * 86400));

        $this->deleteByUserId($userId);

        // ✅ Используем $this->db вместо Database::getConnection()
        $this->db->execute("
            INSERT INTO `{$this->table}` 
            (`user_id`, `selector`, `hashed_validator`, `user_agent`, `ip_address`, `expires_at`)
            VALUES (:user_id, :selector, :hashed_validator, :user_agent, :ip_address, :expires_at)
        ", [
            'user_id' => $userId,
            'selector' => $selector,
            'hashed_validator' => $hashedValidator,
            'user_agent' => $userAgent,
            'ip_address' => $ipAddress,
            'expires_at' => $expiresAt
        ]);

        return [
            'selector' => $selector,
            'validator' => $validator,
            'token' => $token
        ];
    }

    /**
     * Найти токен по селектору
     */
    public function findBySelector(string $selector): ?array
    {
        // ✅ Используем $this->db->fetchOne()
        return $this->db->fetchOne("
            SELECT * FROM `{$this->table}`
            WHERE `selector` = :selector 
            AND `expires_at` > NOW()
            LIMIT 1
        ", ['selector' => $selector]);
    }

    /**
     * Проверить валидность токена
     */
    public function validateToken(string $selector, string $validator): ?array
    {
        $record = $this->findBySelector($selector);

        if (!$record) {
            return null;
        }

        if (!hash_equals($record['hashed_validator'], hash('sha256', $validator))) {
            return null;
        }

        if (strtotime($record['expires_at']) < time()) {
            $this->deleteBySelector($selector);
            return null;
        }

        return $record;
    }

    /**
     * Удалить токен по селектору
     */
    public function deleteBySelector(string $selector): bool
    {
        return $this->db->execute(
            "DELETE FROM `{$this->table}` WHERE `selector` = :selector",
            ['selector' => $selector]
        ) > 0;
    }

    /**
     * Удалить все токены пользователя
     */
    public function deleteByUserId(int $userId): bool
    {
        return $this->db->execute(
            "DELETE FROM `{$this->table}` WHERE `user_id` = :user_id",
            ['user_id' => $userId]
        ) > 0;
    }

    /**
     * Очистить истекшие токены (для cron)
     */
    public function cleanupExpired(): int
    {
        return $this->db->execute("DELETE FROM `{$this->table}` WHERE `expires_at` < NOW()");
    }
}