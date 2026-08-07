<?php

declare(strict_types=1);

namespace App\Modules\SocialAuth\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

class SocialAccount extends Model
{
    protected string $table = 'social_accounts';

    protected array $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'profile_data',
    ];

    /**
     * Найти привязку по провайдеру и ID пользователя у провайдера.
     */
    public function findByProviderUser(string $provider, string $providerUserId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `{$this->table}` 
             WHERE `provider` = :provider AND `provider_user_id` = :provider_user_id
             LIMIT 1",
            ['provider' => $provider, 'provider_user_id' => $providerUserId]
        );
    }

    /**
     * Найти все привязки пользователя.
     */
    public function findByUserId(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->table}` WHERE `user_id` = :user_id ORDER BY `created_at` ASC",
            ['user_id' => $userId]
        );
    }

    /**
     * Привязать социальный аккаунт к пользователю.
     */
    public function attach(int $userId, string $provider, string $providerUserId, array $data = []): bool
    {
        return $this->db->execute(
            "INSERT INTO `{$this->table}` 
                (`user_id`, `provider`, `provider_user_id`, `access_token`, `refresh_token`, `token_expires_at`, `profile_data`)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $provider,
                $providerUserId,
                $data['access_token'] ?? null,
                $data['refresh_token'] ?? null,
                $data['token_expires_at'] ?? null,
                json_encode($data['profile'] ?? [], JSON_UNESCAPED_UNICODE),
            ]
        ) > 0;
    }

    /**
     * Отвязать социальный аккаунт от пользователя.
     * Возвращает false, если это единственная привязка и у пользователя нет пароля.
     */
    public function detach(int $userId, string $provider): bool
    {
        return $this->db->execute(
            "DELETE FROM `{$this->table}` WHERE `user_id` = ? AND `provider` = ?",
            [$userId, $provider]
        ) > 0;
    }

    /**
     * Посчитать количество привязок у пользователя.
     */
    public function countByUserId(int $userId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->table}` WHERE `user_id` = ?",
            [$userId]
        );
    }
}