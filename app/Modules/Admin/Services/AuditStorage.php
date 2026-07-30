<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use W3a\Core\Contracts\AuditStorageInterface;
use W3a\Core\Database\Database;

/**
 * Реализация хранилища аудита.
 * Работает с таблицей audit_logs в базе данных.
 */
class AuditStorage implements AuditStorageInterface
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function log(
        int $userId,
        string $username,
        string $role,
        string $ipAddress,
        string $action,
        string $description,
        string $category,
        ?string $payload
    ): void {
        $this->db->query(
            "INSERT INTO audit_logs 
                (user_id, username, role, ip_address, action, description, category, payload, created_at) 
             VALUES (:user_id, :username, :role, :ip_address, :action, :description, :category, :payload, NOW())",
            [
                'user_id'     => $userId,
                'username'    => $username,
                'role'        => $role,
                'ip_address'  => $ipAddress,
                'action'      => $action,
                'description' => $description,
                'category'    => $category,
                'payload'     => $payload,
            ]
        );
    }

    public function getByCategory(string $category, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM audit_logs 
             WHERE category = :category
             ORDER BY id DESC 
             LIMIT :limit OFFSET :offset",
            [
                'category' => $category,
                'limit'    => $limit,
                'offset'   => $offset,
            ]
        );
    }

    public function getAll(int $limit = 100, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM audit_logs 
             ORDER BY id DESC 
             LIMIT :limit OFFSET :offset",
            [
                'limit'  => $limit,
                'offset' => $offset,
            ]
        );
    }

    public function countByCategory(string $category): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs WHERE category = :category",
            ['category' => $category]
        );
    }

    public function countAll(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs");
    }

    public function cleanup(int $days = 90): int
    {
        return $this->db->execute(
            "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)",
            ['days' => $days]
        );
    }
}