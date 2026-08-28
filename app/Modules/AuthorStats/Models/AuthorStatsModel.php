<?php

declare(strict_types=1);

namespace App\Modules\AuthorStats\Models;

use W3a\Core\Database\Database;

class AuthorStatsModel
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getTotalViews(int $userId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `story_views` sv
             JOIN `stories` s ON s.id = sv.story_id
             WHERE s.user_id = :uid AND s.deleted_at IS NULL",
            ['uid' => $userId]
        );
    }

    public function getUniqueReaders(int $userId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(DISTINCT sv.user_id) FROM `story_views` sv
             JOIN `stories` s ON s.id = sv.story_id
             WHERE s.user_id = :uid AND s.deleted_at IS NULL",
            ['uid' => $userId]
        );
    }

    public function getAvgReadTime(int $userId): float
    {
        return (float)$this->db->fetchColumn(
            "SELECT AVG(sv.read_seconds) FROM `story_views` sv
             JOIN `stories` s ON s.id = sv.story_id
             WHERE s.user_id = :uid AND s.deleted_at IS NULL",
            ['uid' => $userId]
        );
    }

    public function getTotalClapsReceived(int $userId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(v.claps), 0) FROM `votes` v
             JOIN `stories` s ON s.id = v.votable_id AND v.votable_type = 'story'
             WHERE s.user_id = :uid AND s.deleted_at IS NULL",
            ['uid' => $userId]
        );
    }

    public function getStoriesStats(int $userId): array
    {
        $sql = "
            SELECT
                s.id,
                s.title,
                s.created_at,
                s.score,
                s.reading_time,
                s.comments_count,
                COALESCE(views.cnt, 0) as views,
                COALESCE(views.avg_sec, 0) as avg_seconds,
                COALESCE(claps.cnt, 0) as claps
            FROM `stories` s
            LEFT JOIN (
                SELECT story_id, COUNT(*) as cnt, AVG(read_seconds) as avg_sec
                FROM `story_views`
                GROUP BY story_id
            ) views ON views.story_id = s.id
            LEFT JOIN (
                SELECT votable_id, SUM(claps) as cnt
                FROM `votes`
                WHERE votable_type = 'story'
                GROUP BY votable_id
            ) claps ON claps.votable_id = s.id
            WHERE s.user_id = :uid AND s.deleted_at IS NULL AND s.status = 'published'
            ORDER BY s.created_at DESC
        ";

        return $this->db->fetchAll($sql, ['uid' => $userId]) ?: [];
    }

    public function getRecentReaders(int $userId, int $limit = 20): array
    {
        $sql = "
            SELECT DISTINCT sv.user_id, u.username, up.avatar, MAX(sv.updated_at) as last_read
            FROM `story_views` sv
            JOIN `stories` s ON s.id = sv.story_id
            JOIN `users` u ON u.id = sv.user_id
            LEFT JOIN `user_profiles` up ON up.user_id = u.id
            WHERE s.user_id = :uid AND s.deleted_at IS NULL AND s.status = 'published'
            GROUP BY sv.user_id
            ORDER BY last_read DESC
            LIMIT :lim
        ";

        return $this->db->fetchAll($sql, ['uid' => $userId, 'lim' => $limit]) ?: [];
    }
}