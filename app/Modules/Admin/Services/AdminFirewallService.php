<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use W3a\Core\Database\Database;
use W3a\Core\Support\Audit;

/**
 * Сервис для управления IP-баном (Firewall).
 * 
 * ✅ ИЗМЕНЕНО: Все зависимости обязательны и внедряются через конструктор.
 */
class AdminFirewallService
{
    private Database $db;
    private Audit $audit;

    public function __construct(Database $db, Audit $audit)
    {
        $this->db = $db;
        $this->audit = $audit;
    }

    public function getBannedIps(): array
    {
        return $this->db->fetchAll("SELECT * FROM `banned_ips` ORDER BY id DESC");
    }

    public function banIp(string $ip, string $reason = 'Нарушение правил сообщества'): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        try {
            $this->db->execute(
                "INSERT INTO `banned_ips` (`ip_address`, `reason`) VALUES (:ip, :reason)",
                ['ip' => $ip, 'reason' => $reason]
            );

            $this->audit->log('admin.ip_banned', "Администратор заблокировал IP: {$ip}", 'admin');

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function unbanIp(int $id): ?string
    {
        $ip = $this->db->fetchColumn(
            "SELECT `ip_address` FROM `banned_ips` WHERE `id` = :id",
            ['id' => $id]
        );

        if (!$ip) {
            return null;
        }

        $this->db->execute("DELETE FROM `banned_ips` WHERE `id` = :id", ['id' => $id]);

        $this->audit->log('admin.ip_unbanned', "Администратор разблокировал IP: {$ip}", 'admin');

        return $ip;
    }
}