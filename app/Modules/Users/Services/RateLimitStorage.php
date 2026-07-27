<?php

declare(strict_types=1);

namespace App\Modules\Users\Services;

use W3a\Core\Contracts\RateLimitStorageInterface;
use W3a\Core\Database;
use W3a\Core\Config;

class RateLimitStorage implements RateLimitStorageInterface
{
    public function __construct(
        private readonly Database $db,
        private readonly Config $config // <-- Добавили Config
    ) {}

    public function incrementAndGet(string $identifier, string $action, int $windowSeconds): int
    {
        // ✅ Вероятностная очистка мусора (использует конфиг!)
        $gcProbability = $this->config->getInt('rate_limit.gc_probability', 0);
        if ($gcProbability > 0 && random_int(1, 100) <= $gcProbability) {
            // Удаляем записи старше 1 часа (безопасный запас для любого window)
            $this->db->execute("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        }

        $now = time();
        $windowStart = date('Y-m-d H:i:s', $now - ($now % $windowSeconds));
        
        $this->db->query(
            "INSERT INTO rate_limits (identifier, endpoint_action, window_start, request_count) 
             VALUES (:identifier, :action, :window_start, 1) 
             ON DUPLICATE KEY UPDATE request_count = request_count + 1",
            ['identifier' => $identifier, 'action' => $action, 'window_start' => $windowStart]
        );

        $stmt = $this->db->query(
            "SELECT request_count FROM rate_limits WHERE identifier = :identifier AND endpoint_action = :action AND window_start = :window_start",
            ['identifier' => $identifier, 'action' => $action, 'window_start' => $windowStart]
        );
        
        return (int)($stmt->fetchColumn() ?: 1);
    }
}