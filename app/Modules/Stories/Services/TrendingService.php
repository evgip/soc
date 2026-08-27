<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use W3a\Core\Database\Database;
use W3a\Core\Cache\FileCache;

class TrendingService
{
    private Database $db;
    private FileCache $cache;
    private TagAttachmentService $tagAttachment;

    private const CACHE_TTL = 300;
    private const CACHE_KEY = 'trending_stories_v1';

    public function __construct(Database $db, FileCache $cache, TagAttachmentService $tagAttachment)
    {
        $this->db = $db;
        $this->cache = $cache;
        $this->tagAttachment = $tagAttachment;
    }

    public function getTrending(int $limit = 5): array
    {
        $cacheKey = self::CACHE_KEY . '_' . $limit;

        return $this->cache->remember(
            $cacheKey,
            self::CACHE_TTL,
            fn() => $this->fetchTrendingFromDb($limit)
        );
    }

    public function invalidateCache(): void
    {
        $this->cache->forgetMany([
            self::CACHE_KEY . '_3',
            self::CACHE_KEY . '_5',
            self::CACHE_KEY . '_10',
        ]);
    }

    private function fetchTrendingFromDb(int $limit): array
    {
        $sql = "
            SELECT 
                s.*,
                u.username as author_name,
                up.avatar as author_avatar,
                COUNT(DISTINCT v.id) as votes_24h,
                COUNT(DISTINCT c.id) as comments_24h,
                (
                    LOG10(COUNT(DISTINCT v.id) + 1) * 10 +
                    COUNT(DISTINCT c.id) * 2 -
                    TIMESTAMPDIFF(HOUR, s.created_at, NOW()) * 0.5
                ) as trending_score
            FROM `stories` s
            JOIN `users` u ON s.user_id = u.id
            LEFT JOIN `user_profiles` up ON u.id = up.user_id
            LEFT JOIN `votes` v ON v.votable_type = 'story' 
                AND v.votable_id = s.id 
                AND v.created_at >= NOW() - INTERVAL 24 HOUR
            LEFT JOIN `comments` c ON c.story_id = s.id 
                AND c.created_at >= NOW() - INTERVAL 24 HOUR
                AND c.deleted_at IS NULL
            WHERE s.deleted_at IS NULL
              AND s.created_at >= NOW() - INTERVAL 48 HOUR
            GROUP BY s.id
            HAVING trending_score > 0
            ORDER BY trending_score DESC, s.created_at DESC
            LIMIT ?
        ";

        $stmt = $this->db->query($sql, [$limit]);
        $stories = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return $this->tagAttachment->attach($stories);
    }
}