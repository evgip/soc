<?php

namespace App\Modules\Origins\Models;

use W3a\Core\Model;
use W3a\Core\Database;
use W3a\Core\Logger;

class Domain extends Model
{
    protected string $table = 'domains';

    protected array $fillable = [
        'domain',
        'status',
        'ban_reason',
        'banned_by',
        'deleted_at',
    ];

    /**
     * Проверить, забанен ли домен
     */
    public function isBanned(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        if (empty($domain)) {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM `{$this->table}`
                WHERE LOWER(`domain`) = :domain
                  AND `status` = 'banned'
                  AND `deleted_at` IS NULL";

        return (int)$this->db->fetchColumn($sql, ['domain' => $domain]) > 0;
    }

    /**
     * Получить информацию о бане домена (если забанен)
     */
    public function getBanInfo(string $domain): ?array
    {
        $domain = strtolower(trim($domain));
        if (empty($domain)) {
            return null;
        }

        $sql = "SELECT d.*, u.username AS banned_by_name
                FROM `{$this->table}` d
                LEFT JOIN `users` u ON d.banned_by = u.id
                WHERE LOWER(d.`domain`) = :domain
                  AND d.`status` = 'banned'
                  AND d.`deleted_at` IS NULL
                LIMIT 1";

        return $this->db->fetchOne($sql, ['domain' => $domain]);
    }

    /**
     * Забанить домен (обновляет существующую запись или создает новую).
     * Предотвращает ошибку 1062 Duplicate entry.
     */
    public function ban(string $domain, string $reason, int $bannedBy): bool
    {
        $domain = strtolower(trim($domain));

        // 1. Надежный поиск записи через прямой SQL (в обход возможных багов Model::findBy)
        $sql = "SELECT `id`, `status` FROM `{$this->table}` 
                WHERE LOWER(`domain`) = :domain 
                LIMIT 1";
        
        $existing = $this->db->fetchOne($sql, ['domain' => $domain]);

        $banData = [
            'domain'     => $domain,
            'status'     => 'banned',
            'ban_reason' => $reason,
            'banned_by'  => $bannedBy,
            'deleted_at' => null,
        ];

        if ($existing) {
            // 2. Если запись найдена — обновляем её по найденному ID
            // Это гарантирует UPDATE, а не INSERT
            return $this->update($existing['id'], $banData);
        }

        // 3. Если записи действительно нет в базе — создаем новую
        return $this->create($banData) > 0;
    }

    /**
     * Разбанить домен (сбрасывает статус и очищает данные о бане).
     */
    public function unban(string $domain): bool
    {
        $domain = strtolower(trim($domain));

        // Просто меняем статус на 'allowed' и очищаем причину бана.
        // Мы НЕ трогаем deleted_at, чтобы домен оставался в истории базы данных,
        // но перестал считаться заблокированным.
        $sql = "UPDATE `{$this->table}`
                SET `status` = 'allowed', 
                    `ban_reason` = NULL, 
                    `banned_by` = NULL
                WHERE LOWER(`domain`) = :domain";

        return $this->db->execute($sql, ['domain' => $domain]) > 0;
    }

    /**
     * Получить список всех забаненных доменов с информацией о модераторе
     */
    public function getBannedDomains(): array
    {
        $sql = "SELECT d.*, u.username AS banned_by_name
                FROM `{$this->table}` d
                LEFT JOIN `users` u ON d.banned_by = u.id
                WHERE d.`status` = 'banned'
                  AND d.`deleted_at` IS NULL
                ORDER BY d.created_at DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Получить все домены (включая разрешённые) — для админки
     */
    public function getAllDomains(): array
    {
        $sql = "SELECT d.*, u.username AS banned_by_name
                FROM `{$this->table}` d
                LEFT JOIN `users` u ON d.banned_by = u.id
                WHERE d.`deleted_at` IS NULL
                ORDER BY d.created_at DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Подсчитать количество забаненных доменов
     */
    public function getBannedCount(): int
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}`
                WHERE `status` = 'banned' AND `deleted_at` IS NULL";

        return (int)$this->db->fetchColumn($sql);
    }
}