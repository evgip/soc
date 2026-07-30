<?php

namespace App\Modules\Moderations\Models;

use W3a\Core\Database\Model;

class ModActivity extends Model
{
    protected string $table = 'mod_activity';

    protected array $fillable = [
        'moderator_id',
        'action',
        'date',
        'count',
    ];

    // Нет deleted_at
    protected bool $includeTrashed = true;

    protected function applySoftDeleteConstraint(string $sql): string
    {
        return $sql;
    }

    /**
     * Инкремент счётчика за сегодня (INSERT ON DUPLICATE KEY UPDATE)
     */
    public function incrementToday(int $moderatorId, string $action): void
    {
        $today = date('Y-m-d');

        $sql = "INSERT INTO `mod_activity` (`moderator_id`, `action`, `date`, `count`) 
                VALUES (:mod_id, :action, :date, 1)
                ON DUPLICATE KEY UPDATE `count` = `count` + 1";

        $this->db->execute($sql, [
            'mod_id' => $moderatorId,
            'action' => $action,
            'date'   => $today,
        ]);
    }

    /**
     * Получить статистику активности за последние N дней
     */
    public function getStats(int $days = 30): array
    {
        $sql = "SELECT u.username AS moderator_name, ma.action, SUM(ma.count) AS total, ma.date
                FROM `mod_activity` ma
                LEFT JOIN `users` u ON u.id = ma.moderator_id
                WHERE ma.`date` >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY ma.moderator_id, ma.action, ma.date
                ORDER BY ma.date DESC, total DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Сводная таблица: модератор → общее количество действий
     */
    public function getLeaderboard(int $days = 30): array
    {
        $sql = "SELECT u.username AS moderator_name, SUM(ma.count) AS total_actions
                FROM `mod_activity` ma
                LEFT JOIN `users` u ON u.id = ma.moderator_id
                WHERE ma.`date` >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY ma.moderator_id
                ORDER BY total_actions DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
