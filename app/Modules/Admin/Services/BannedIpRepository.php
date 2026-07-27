<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use W3a\Core\Contracts\BannedIpRepositoryInterface;
use W3a\Core\Database;

/**
 * Реализация репозитория заблокированных IP.
 */
class BannedIpRepository implements BannedIpRepositoryInterface
{
    public function __construct(
        private readonly Database $db
    ) {
    }

    public function getBanReason(string $ip): ?string
    {
        $stmt = $this->db->query(
            "SELECT `reason` FROM `banned_ips` WHERE `ip_address` = :ip LIMIT 1",
            ['ip' => $ip]
        );
        
        $reason = $stmt->fetchColumn();

        // Если причина найдена (не false), возвращаем её как строку. Иначе null.
        return $reason !== false ? (string)$reason : null;
    }
}