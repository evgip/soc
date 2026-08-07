<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use W3a\Core\Database\Database;
use App\Modules\Common\Support\CacheHelper;

/**
 * Сервис "Выбор редакции" (Staff Picks)
 * 
 * Отвечает за статьи, вручную отобранные администраторами
 * за исключительное качество, глубину и ценность.
 * 
 * Два режима работы:
 * 1. getStaffPicks(limit) — для сайдбара на главной, КЭШИРУЕТСЯ на 15 мин
 * 2. getAllStaffPicks(limit, offset) — для страницы /staff-picks, БЕЗ кэша
 * 
 * Переключение статуса (toggleStaffPick) автоматически инвалидирует кэш,
 * чтобы изменения мгновенно отображались у всех пользователей.
 */
class StaffPicksService
{
    private Database $db;
    private CacheHelper $cache;

    /**
     * Время жизни кэша для сайдбара — 15 минут.
     * Staff Picks меняются редко (раз в несколько дней),
     * поэтому можно кэшировать надолго.
     */
    private const CACHE_TTL = 900;

    /**
     * Базовый ключ кэша. Версия (v1) позволяет легко сбросить весь кэш
     * при изменении структуры данных — просто увеличиваем до v2.
     */
    private const CACHE_KEY = 'staff_picks_sidebar_v1';

    public function __construct(Database $db, CacheHelper $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * Получить выбор редакции для САЙДБАРА (с кэшированием).
     * 
     * Используется на главной странице в правой колонке.
     * Возвращает 3-5 лучших статей, отсортированных по picked_at DESC.
     * 
     * @param int $limit Сколько статей вернуть (по умолчанию 3)
     * @return array Массив статей с тегами
     */
    public function getStaffPicks(int $limit = 3): array
    {
        $cacheKey = self::CACHE_KEY . '_' . $limit;

        // Кэшируем через CacheHelper::remember() — при промахе выполнится запрос
        return $this->cache->remember(
            $cacheKey,
            self::CACHE_TTL,
            fn() => $this->fetchStaffPicksFromDb($limit)
        );
    }

    /**
     * Получить ВСЕ Staff Picks с пагинацией (для отдельной страницы /staff-picks).
     * 
     * НЕ кэшируем, потому что:
     * - Страница открывается нечасто
     * - Нужна актуальная пагинация
     * - Админы часто меняют статус, кэш будет постоянно инвалидироваться
     * 
     * @param int $limit Записей на страницу (по умолчанию 12 для сетки 3×4)
     * @param int $offset Смещение для пагинации
     * @return array Массив статей с тегами
     */
    public function getAllStaffPicks(int $limit = 12, int $offset = 0): array
    {
        $sql = "
            SELECT 
                s.*, 
                u.username as author_name, 
                up.avatar as author_avatar
            FROM `stories` s
            JOIN `users` u ON s.user_id = u.id
            LEFT JOIN `user_profiles` up ON u.id = up.user_id
            WHERE s.is_staff_pick = 1 
              AND s.deleted_at IS NULL
            ORDER BY s.picked_at DESC, s.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->db->query($sql, [$limit, $offset]);
        $stories = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return $this->attachTags($stories);
    }

    /**
     * Получить общее количество Staff Picks (для пагинации).
     * 
     * @return int Общее число статей в выборе редакции
     */
    public function getTotalStaffPicksCount(): int
    {
        return (int)$this->db->fetchColumn("
            SELECT COUNT(*) 
            FROM `stories` 
            WHERE is_staff_pick = 1 
              AND deleted_at IS NULL
        ");
    }

    /**
     * Переключить статус "Выбор редакции" для статьи.
     * 
     * Логика:
     * 1. Проверяем текущее значение is_staff_pick
     * 2. Если 1 → ставим 0 (убираем из выбора)
     * 3. Если 0 → ставим 1 и picked_at = NOW() (добавляем в начало)
     * 4. При успехе инвалидируем кэш, чтобы изменения сразу отобразились
     * 
     * @param int $storyId ID статьи
     * @return bool true если переключение успешно
     */
    public function toggleStaffPick(int $storyId): bool
    {
        // Получаем текущий статус (один лёгкий SELECT)
        $story = $this->db->fetchOne(
            "SELECT `is_staff_pick` FROM `stories` WHERE `id` = ?",
            [$storyId]
        );

        if (!$story) {
            return false;
        }

        $isCurrentlyPick = (bool)($story['is_staff_pick'] ?? false);

        // Переключаем статус
        if ($isCurrentlyPick) {
            // Убираем из выбора
            $result = $this->db->execute(
                "UPDATE `stories` 
                 SET `is_staff_pick` = 0, `picked_at` = NULL 
                 WHERE `id` = ?",
                [$storyId]
            ) > 0;
        } else {
            // Добавляем в выбор с текущей датой (чтобы появилась вверху)
            $result = $this->db->execute(
                "UPDATE `stories` 
                 SET `is_staff_pick` = 1, `picked_at` = NOW() 
                 WHERE `id` = ?",
                [$storyId]
            ) > 0;
        }

        // При успехе сбрасываем кэш — иначе пользователи увидят старое ещё 15 минут
        if ($result) {
            $this->invalidateCache();
        }

        return $result;
    }

    /**
     * Инвалидировать кэш сайдбара.
     * 
     * Вызывается при:
     * - toggleStaffPick() — админ добавил/убрал статью
     * - adminDelete() — статья удалена
     * - adminRestore() — статья восстановлена
     * 
     * Удаляем все известные варианты кэша (3 и 5 записей).
     */
    public function invalidateCache(): void
    {
        $this->cache->forgetMany([
            self::CACHE_KEY . '_3',
            self::CACHE_KEY . '_5',
        ]);
    }

    /**
     * Реальный SQL-запрос к БД для сайдбара (без кэша).
     * 
     * @param int $limit Количество статей
     * @return array Массив статей с тегами
     */
    private function fetchStaffPicksFromDb(int $limit): array
    {
        $sql = "
            SELECT 
                s.*, 
                u.username as author_name, 
                up.avatar as author_avatar
            FROM `stories` s
            JOIN `users` u ON s.user_id = u.id
            LEFT JOIN `user_profiles` up ON u.id = up.user_id
            WHERE s.is_staff_pick = 1 
              AND s.deleted_at IS NULL
            ORDER BY s.picked_at DESC, s.created_at DESC
            LIMIT ?
        ";

        $stmt = $this->db->query($sql, [$limit]);
        $stories = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return $this->attachTags($stories);
    }

    /**
     * Прикрепить теги к массиву статей одним batch-запросом.
     * 
     * @param array $stories Массив статей
     * @return array Статьи с полем 'tags_with_names'
     */
    private function attachTags(array $stories): array
    {
        if (empty($stories)) {
            return [];
        }

        $storyIds = array_column($stories, 'id');
        $placeholders = implode(',', array_fill(0, count($storyIds), '?'));

        $sql = "
            SELECT tg.story_id, t.slug, t.name
            FROM `taggings` tg
            JOIN `tags` t ON tg.tag_id = t.id
            WHERE tg.story_id IN ($placeholders)
            ORDER BY t.slug ASC
        ";

        $stmt = $this->db->query($sql, $storyIds);
        $tagsData = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Группируем теги по story_id
        $tagsByStory = [];
        foreach ($tagsData as $tag) {
            $tagsByStory[(int)$tag['story_id']][] = [
                'slug' => $tag['slug'],
                'name' => $tag['name'],
            ];
        }

        // Прикрепляем теги
        foreach ($stories as &$story) {
            $story['tags_with_names'] = $tagsByStory[(int)$story['id']] ?? [];
        }

        return $stories;
    }
}