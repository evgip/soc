<?php

declare(strict_types=1);

namespace App\Modules\Common\Storage;

use W3a\Core\Contracts\RateLimitStorageInterface;
use W3a\Core\Database\Database;

class DatabaseRateLimitStorage implements RateLimitStorageInterface
{
    public function __construct(
        private readonly Database $db
    ) {}

    public function incrementAndGet(string $identifier, string $action, int $windowSeconds): int
    {
        $windowStart = date('Y-m-d H:i:00');

        $this->db->execute(
            "INSERT INTO rate_limits (identifier, endpoint_action, window_start, request_count) 
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE request_count = request_count + 1",
            [$identifier, $action, $windowStart]
        );

        $windowStartTime = date('Y-m-d H:i:s', time() - $windowSeconds);

        return (int) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(request_count), 0) 
             FROM rate_limits 
             WHERE identifier = ? AND endpoint_action = ? AND window_start >= ?",
            [$identifier, $action, $windowStartTime]
        );
    }
}