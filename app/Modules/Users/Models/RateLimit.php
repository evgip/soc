<?php

namespace App\Modules\Users\Models;

use W3a\Core\Model;

class RateLimit extends Model
{
    protected string $table = 'rate_limits';

    // request_count и window_start будут заполняться автоматически через SQL
    protected array $fillable = [
        'identifier',
        'endpoint_action',
    ];

    /**
     * Атомарно увеличивает счетчик запросов для данного окна.
     * Если записи нет — создает её со счетчиком 1.
     * Если запись есть — увеличивает счетчик на 1.
     * 
     * @return int Новое значение счетчика после обновления
     */
    public function incrementRequestCount(string $identifier, string $action, int $windowSeconds): int
    {
        $windowStart = date('Y-m-d H:i:s', floor(time() / $windowSeconds) * $windowSeconds);

        $sql = "INSERT INTO `{$this->table}` 
                    (`identifier`, `endpoint_action`, `window_start`, `request_count`)
                VALUES 
                    (:identifier, :action, :window_start, 1)
                ON DUPLICATE KEY UPDATE 
                    `request_count` = `request_count` + 1";

        try {
            $this->db->execute($sql, [
                'identifier'   => $identifier,
                'action'       => $action,
                'window_start' => $windowStart,
            ]);
        } catch (\PDOException $e) {
            // Если это ошибка дубликата (23000), мы безопасно игнорируем её.
            // Это означает, что параллельный запрос уже создал запись, 
            // и мы просто прочитаем актуальное значение ниже.
            if ($e->getCode() !== '23000') {
                // Любые другие ошибки БД (например, синтаксические) должны быть проброшены
                throw $e; 
            }
        }

        // Возвращаем актуальное значение счетчика
        $count = $this->db->fetchColumn(
            "SELECT `request_count` FROM `{$this->table}` 
             WHERE `identifier` = :identifier 
               AND `endpoint_action` = :action 
               AND `window_start` = :window_start",
            [
                'identifier'   => $identifier,
                'action'       => $action,
                'window_start' => $windowStart,
            ]
        );

        // Гарантированно возвращаем число (0, если запись вдруг не найдена)
        return (int)($count ?: 0);
    }

    /**
     * Фоновая очистка устаревших окон (вызывать по крону или редко, а не случайно!)
     */
    public function clearStaleWindows(int $retentionSeconds): void
    {
        $this->db->execute(
            "DELETE FROM `{$this->table}` 
             WHERE `window_start` < NOW() - INTERVAL :retention SECOND",
            ['retention' => $retentionSeconds]
        );
    }
}