<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

/**
 * Модель для работы с попытками аутентификации.
 * Инкапсулирует все SQL-запросы, связанные с защитой от брутфорса.
 */
class AuthAttempt
{
    private Database $db;
    private Logger $logger;

    public function __construct(Database $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Считает количество неудачных попыток входа с указанного IP
     * за последние N минут.
     */
    public function countFailedByIp(string $ip, int $minutes): int
    {
        return (int) $this->db->fetchColumn("
            SELECT COUNT(*) 
            FROM audit_logs 
            WHERE action = 'auth.login_failed' 
              AND ip_address = ?
              AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ", [$ip, $minutes]);
    }

    /**
     * Считает количество неудачных попыток входа для указанного email
     * за последние N минут.
     */
    public function countFailedByEmail(string $email, int $minutes): int
    {
        return (int) $this->db->fetchColumn("
            SELECT COUNT(*) 
            FROM audit_logs 
            WHERE action = 'auth.login_failed' 
              AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.email')) = ?
              AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ", [$email, $minutes]);
    }

    /**
     * Очищает историю неудачных попыток для IP и Email.
     */
    public function clearForIpAndEmail(string $ip, string $email): void
    {
        $this->db->execute("
            DELETE FROM audit_logs 
            WHERE action = 'auth.login_failed' 
              AND (
                  ip_address = ? 
                  OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.email')) = ?
              )
        ", [$ip, $email]);
    }

    /**
     * Возвращает время последней неудачной попытки для IP.
     */
    public function getLastFailedAttemptTime(string $ip, int $minutes): ?string
    {
        $result = $this->db->fetchColumn("
            SELECT created_at 
            FROM audit_logs 
            WHERE action = 'auth.login_failed' 
              AND ip_address = ?
              AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY created_at DESC
            LIMIT 1
        ", [$ip, $minutes]);

        return $result ?: null;
    }
}