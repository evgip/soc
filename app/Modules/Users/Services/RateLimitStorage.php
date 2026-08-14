<?php

declare(strict_types=1);

namespace App\Modules\Users\Services;

use W3a\Core\Contracts\RateLimitStorageInterface;
use W3a\Core\Database\Database;
use W3a\Core\Foundation\Config;

class RateLimitStorage implements RateLimitStorageInterface
{
    public function __construct(
        private readonly Database $db,
        private readonly Config $config // <-- Добавили Config
    ) {}


	public function incrementAndGet(string $identifier, string $action, int $windowSeconds): int
	{
		// Вероятностная очистка мусора
		$gcProbability = $this->config->getInt('rate_limit.gc_probability', 0);
		if ($gcProbability > 0 && random_int(1, 100) <= $gcProbability) {
			// Находим максимальный window из всех правил
			// чтобы не удалить записи, чьё окно ещё не закончилось
			$rules = $this->config->getArray('rate_limit.rules', []);
			$maxWindow = 86400; // дефолт 24 часа
			foreach ($rules as $rule) {
				if (!empty($rule['window'])) {
					$maxWindow = max($maxWindow, (int) $rule['window']);
				}
			}
			
			// Удаляем записи старше максимального окна
			$this->db->execute(
				"DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL :max_window SECOND)",
				['max_window' => $maxWindow]
			);
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