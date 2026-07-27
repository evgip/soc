<?php

namespace App\Modules\Stats\Models;

use W3a\Core\Model;
use W3a\Core\Database;
use W3a\Core\Logger;
use W3a\Core\SvgChart;

class Stats extends Model
{
    protected string $table = 'users';

    /**
     * Путь к директории кэша
     */
    private function getCachePath(): string
    {
        // Мы находимся в app/Modules/Stats/Models/
        // dirname(__DIR__, 4) поднимается на 4 уровня вверх до корня проекта
        return dirname(__DIR__, 4) . '/storage/cache/';
    }

    public function getTotalUsers(): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `users` WHERE `deleted_at` IS NULL"
        );
    }

    public function getTotalStories(): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `stories` WHERE `deleted_at` IS NULL"
        );
    }

    public function getTotalComments(): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `comments` WHERE `deleted_at` IS NULL"
        );
    }

    public function getTotalVotes(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `votes`");
    }

    public function getUsersByMonth(int $months = 12): array
    {
        $sql = "SELECT DATE_FORMAT(`created_at`, '%Y-%m') AS label,
                       COUNT(*) AS value
                FROM `users`
                WHERE `deleted_at` IS NULL
                  AND `created_at` >= DATE_SUB(NOW(), INTERVAL :months MONTH)
                GROUP BY label
                ORDER BY label ASC";
        
        return $this->db->fetchAll($sql, ['months' => $months]);
    }

    public function getStoriesByMonth(int $months = 12): array
    {
        $sql = "SELECT DATE_FORMAT(`created_at`, '%Y-%m') AS label,
                       COUNT(*) AS value
                FROM `stories`
                WHERE `deleted_at` IS NULL
                  AND `created_at` >= DATE_SUB(NOW(), INTERVAL :months MONTH)
                GROUP BY label
                ORDER BY label ASC";
        
        return $this->db->fetchAll($sql, ['months' => $months]);
    }

    public function getCommentsByMonth(int $months = 12): array
    {
        $sql = "SELECT DATE_FORMAT(`created_at`, '%Y-%m') AS label,
                       COUNT(*) AS value
                FROM `comments`
                WHERE `deleted_at` IS NULL
                  AND `created_at` >= DATE_SUB(NOW(), INTERVAL :months MONTH)
                GROUP BY label
                ORDER BY label ASC";
        
        return $this->db->fetchAll($sql, ['months' => $months]);
    }

    public function getUsersChartSvg(int $months = 12): string
    {
        $data = $this->getUsersByMonth($months);
        
        $cacheKey = 'stats_users_chart_' . $months;
        $cached = $this->getCached($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $chart = new SvgChart(600, 200, 40, '#ac130d', 'rgba(172, 19, 13, 0.1)');
        $svg = $chart->lineChart($data, 'Новые пользователи');
        
        $this->setCache($cacheKey, $svg, 3600);
        return $svg;
    }

    public function getStoriesChartSvg(int $months = 12): string
    {
        $data = $this->getStoriesByMonth($months);
        
        $cacheKey = 'stats_stories_chart_' . $months;
        $cached = $this->getCached($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $chart = new SvgChart(600, 200, 40, '#0077cc', 'rgba(0, 119, 204, 0.1)');
        $svg = $chart->lineChart($data, 'Публикации');
        
        $this->setCache($cacheKey, $svg, 3600);
        return $svg;
    }

    public function getCommentsChartSvg(int $months = 12): string
    {
        $data = $this->getCommentsByMonth($months);
        
        $cacheKey = 'stats_comments_chart_' . $months;
        $cached = $this->getCached($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $chart = new SvgChart(600, 200, 40, '#28a745', 'rgba(40, 167, 69, 0.1)');
        $svg = $chart->lineChart($data, 'Комментарии');
        
        $this->setCache($cacheKey, $svg, 3600);
        return $svg;
    }

    /**
     * Простое кэширование в файлах
     */
    private function getCached(string $key): ?string
    {
        $file = $this->getCachePath() . $key . '.html';
        if (file_exists($file) && (time() - filemtime($file) < 3600)) {
            return file_get_contents($file);
        }
        return null;
    }

    private function setCache(string $key, string $value, int $ttl): void
    {
        $cacheDir = $this->getCachePath();
        
        // Создаём директорию, если её нет
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $file = $cacheDir . $key . '.html';
        file_put_contents($file, $value);
    }
}