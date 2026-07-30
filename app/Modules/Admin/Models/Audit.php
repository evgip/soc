<?php

namespace App\Modules\Admin\Models;

use W3a\Core\Database\Model;

class Audit extends Model
{
    protected string $table = 'audit_logs';

    /**
     * Pulls critical security alert events logged within the last 5 minutes
     * 
     * @return array
     */
    public function getRecentSecurityAlerts(): array
    {
        // Highly optimized index scan locating specific operational severity markers
        $sql = "SELECT id, user_id, action, description, ip_address, created_at 
                FROM `audit_logs` 
                WHERE `action` IN ('security.csrf_failed', 'security.rate_limited', 'auth.failed_bruteforce')
                  AND `created_at` >= NOW() - INTERVAL 5 MINUTE
                ORDER BY id DESC LIMIT 10";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}