<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use W3a\Core\Database\Database;
use App\Modules\Common\Support\CacheHelper;

/**
 * Сервис "Сейчас в тренде" (Trending on Medium)
 * 
 * Вычисляет топ популярных статей за последние 24 часа на основе:
 * - количества голосов за 24ч (логарифмический вес)
 * - количества новых комментариев за 24ч
 * - "возраста" статьи (старые статьи постепенно вытесняются)
 * 
 * Формула trending_score:
 *   LOG10(votes_24h + 1) × 10 + comments_24h × 2 - hours_since_creation × 0.5
 * 
 * Производительность:
 * - Результаты кэшируются через CacheHelper на 5 минут (TTL = 300 сек)
 * - Кэш инвалидируется при каждом новом голосе за статью (через VoteService)
 * - Благодаря кэшу тяжёлый SQL-запрос выполняется не чаще 1 раза в 5 минут,
 *   даже если главная страница открывается 1000 раз в минуту.
 */
class TrendingService
{
    private Database $db;
    private CacheHelper $cache;

    /**
     * Время жизни кэша в секундах (5 минут).
     * Баланс между актуальностью данных и нагрузкой на БД.
     */
    private const CACHE_TTL = 300;

    /**
     * Базовый ключ кэша. К нему добавляется лимит,
     * чтобы разные варианты (3, 5, 10) кэшировались отдельно.
     */
    private const CACHE_KEY = 'trending_stories_v1';

    public function __construct(Database $db, CacheHelper $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * Получить топ N трендовых статей (с кэшированием).
     * 
     * Метод-фасад: сначала ищет в кэше, при промахе — вычисляет из БД
     * и сохраняет результат в кэш для последующих запросов.
     * 
     * @param int $limit Сколько статей вернуть (по умолчанию 5 — для сайдбара)
     * @return array Массив статей с прикреплёнными тегами
     */
    public function getTrending(int $limit = 5): array
    {
        // Формируем уникальный ключ для конкретного лимита
        // Например: "trending_stories_v1_5" или "trending_stories_v1_10"
        $cacheKey = self::CACHE_KEY . '_' . $limit;

        // CacheHelper::remember() — это аналог Laravel Cache::remember():
        // 1. Пытается получить значение из кэша
        // 2. Если есть и не истекло — возвращает
        // 3. Если нет — вызывает callback, сохраняет и возвращает
        return $this->cache->remember(
            $cacheKey,
            self::CACHE_TTL,
            fn() => $this->fetchTrendingFromDb($limit)
        );
    }

    /**
     * Инвалидировать кэш трендов.
     * 
     * Вызывается из VoteService после каждого нового голоса за статью,
     * чтобы тренды обновлялись в течение 5 минут.
     * 
     * Удаляем все известные варианты кэша (для разных limit).
     * Если добавите новые варианты — не забудьте добавить их сюда.
     */
    public function invalidateCache(): void
    {
        $this->cache->forgetMany([
            self::CACHE_KEY . '_3',
            self::CACHE_KEY . '_5',
            self::CACHE_KEY . '_10',
        ]);
    }

    /**
     * Реальный SQL-запрос к БД (без кэша).
     * 
     * Выполняется только при промахе кэша (раз в 5 минут).
     * Использует индексы:
     * - idx_votes_type_id_created (для фильтра голосов по типу и дате)
     * - idx_comments_created (для фильтра комментариев по дате)
     * - idx_stories_created_at (для фильтра статей по свежести)
     * 
     * @param int $limit Сколько записей вернуть
     * @return array Массив статей с прикреплёнными тегами
     */
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
            
            -- Голоса только за последние 24 часа
            LEFT JOIN `votes` v ON v.votable_type = 'story' 
                AND v.votable_id = s.id 
                AND v.created_at >= NOW() - INTERVAL 24 HOUR
            
            -- Комментарии только за последние 24 часа, без удалённых
            LEFT JOIN `comments` c ON c.story_id = s.id 
                AND c.created_at >= NOW() - INTERVAL 24 HOUR
                AND c.deleted_at IS NULL
            
            WHERE s.deleted_at IS NULL
              -- Берём статьи не старше 48 часов, иначе тренд теряет смысл
              AND s.created_at >= NOW() - INTERVAL 48 HOUR
            
            GROUP BY s.id
            
            -- Отфильтровываем статьи без активности (trending_score <= 0)
            HAVING trending_score > 0
            
            ORDER BY trending_score DESC, s.created_at DESC
            LIMIT ?
        ";

        $stmt = $this->db->query($sql, [$limit]);
        $stories = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Прикрепляем теги к каждой статье (batch-запрос для производительности)
        return $this->attachTags($stories);
    }

    /**
     * Прикрепляет теги к массиву статей одним batch-запросом.
     * 
     * Почему batch, а не N+1:
     * - Плохо: для каждой из 5 статей делать отдельный SELECT → 6 запросов
     * - Хорошо: один SELECT ... WHERE story_id IN (1,2,3,4,5) → 2 запроса
     * 
     * @param array $stories Массив статей
     * @return array Тот же массив, но с полем 'tags_with_names' у каждой статьи
     */
    private function attachTags(array $stories): array
    {
        if (empty($stories)) {
            return [];
        }

        // Собираем все ID статей
        $storyIds = array_column($stories, 'id');
        $placeholders = implode(',', array_fill(0, count($storyIds), '?'));

        // Один запрос для всех тегов всех статей
        $sql = "
            SELECT tg.story_id, t.slug, t.name
            FROM `taggings` tg
            JOIN `tags` t ON tg.tag_id = t.id
            WHERE tg.story_id IN ($placeholders)
            ORDER BY t.slug ASC
        ";

        $stmt = $this->db->query($sql, $storyIds);
        $tagsData = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Группируем теги по story_id для быстрого поиска
        $tagsByStory = [];
        foreach ($tagsData as $tag) {
            $storyId = (int)$tag['story_id'];
            $tagsByStory[$storyId][] = [
                'slug' => $tag['slug'],
                'name' => $tag['name'],
            ];
        }

        // Прикрепляем теги к каждой статье
        foreach ($stories as &$story) {
            $story['tags_with_names'] = $tagsByStory[(int)$story['id']] ?? [];
        }

        return $stories;
    }
}