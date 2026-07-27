<?php

declare(strict_types=1);

namespace App\Modules\Stories\Models;

use W3a\Core\Model;
use W3a\Core\Database;
use W3a\Core\Logger;
use App\Modules\Stories\Services\RankingService; 

class Story extends Model
{
    protected string $table = 'stories';
    private RankingService $rankingService;

    protected array $fillable = [
        'user_id',
        'title',
        'url',
        'text',
        'description',
        'rejected_fields',
        'user_is_following',
        'domain',
        'score',
        'comments_count',
        'deleted_at'
    ];

    /**
     * Конструктор с инъекцией RankingService
     */
    public function __construct(
        Database $db, 
        Logger $logger, 
        ?RankingService $rankingService = null
    ) {
        parent::__construct($db, $logger);
        $this->rankingService = $rankingService ?? new RankingService();
    }

    private function buildFeedConditions(
        string $tagslug = '', ?string $domain = '', array $excludeTagIds = [],
        string $author = '', array $mutedUserIds = [], bool $showDeleted = false
    ): array {
        $where = [];
        $bindings = [];

        if (!$showDeleted) $where[] = "s.deleted_at IS NULL";
        if ($tagslug !== '') {
            $where[] = "t.slug = :slug";
            $bindings[':slug'] = $tagslug;
        }
        if ($domain !== null && $domain !== '') {
            $where[] = "s.domain = :domain";
            $bindings[':domain'] = $domain;
        }
        if ($author !== '') {
            $where[] = "u.username = :author";
            $bindings[':author'] = $author;
        }

        // Используем новый метод Database для генерации IN (...)
        if (!empty($mutedUserIds)) {
            $inData = $this->db->buildInClause($mutedUserIds, 'muted_user');
            $where[] = "s.user_id NOT IN ({$inData['clause']})";
            $bindings = array_merge($bindings, $inData['bindings']);
        }

        if (!empty($excludeTagIds)) {
            $inData = $this->db->buildInClause($excludeTagIds, 'exclude_tag');
            $where[] = "s.id NOT IN (
                SELECT DISTINCT story_id FROM taggings 
                WHERE tag_id IN ({$inData['clause']})
            )";
            $bindings = array_merge($bindings, $inData['bindings']);
        }

        return ['conditions' => $where, 'bindings' => $bindings];
    }

    public function getFeed(
        int $limit, int $offset, string $tagslug = '', bool $showDeleted = false, 
        ?string $domain = '', array $excludeTagIds = [], string $sort = 'hot',
        string $author = '', array $mutedUserIds = []
    ): array {
        $repo = new \App\Modules\Stories\Repositories\StoryRepository($this->db);
        
        $conditions = $this->buildFeedConditions($tagslug, $domain, $excludeTagIds, $author, $mutedUserIds, $showDeleted);

        $orderBy = match ($sort) {
            'new' => 's.created_at DESC',
            'top' => 's.score DESC, s.created_at DESC',
            default => 's.hotness DESC',
        };

        return $repo->withAuthor()->withAvatar()->withTags()
                    ->addWheres($conditions['conditions'], $conditions['bindings'])
                    ->setOrderBy($orderBy)
                    ->paginate($limit, $offset)
                    ->get();
    }

    public function getTotalCount(
        string $tagslug = '', ?string $domain = '', array $excludeTagIds = [],
        string $author = '', array $mutedUserIds = []
    ): int {
        $repo = new \App\Modules\Stories\Repositories\StoryRepository($this->db);
        
        // Подключаем теги только если они используются в фильтрах (экономим ресурсы БД)
        if ($tagslug !== '' || !empty($excludeTagIds)) {
            $repo->withTags(); 
        }
        
        $conditions = $this->buildFeedConditions($tagslug, $domain, $excludeTagIds, $author, $mutedUserIds);

        return $repo->withAuthor()
                    ->addWheres($conditions['conditions'], $conditions['bindings'])
                    ->count();
    }

    public function getSingleWithAuthor(int $id, bool $showDeleted = false): ?array {
        $repo = new \App\Modules\Stories\Repositories\StoryRepository($this->db);
        
        $repo->withAuthor()->withAvatar()->withTags()
             ->addWhere('s.id = :id', ['id' => $id]);
             
        if (!$showDeleted) {
            $repo->addWhere('s.deleted_at IS NULL');
        }
        
        return $repo->first();
    }

    /**
     * Пересчитать и сохранить hotness для истории.
     */
    public function recalculateHotness(int $storyId): void
    {
        $story = $this->find($storyId);
        if (!$story) return;

        $tagMods = $this->getTagHotnessMods($storyId);

        // Используем сервис вместо глобальной функции
        $hotness = $this->rankingService->calculateHotness(
            (int)$story['score'], 
            $story['created_at'], 
            $tagMods
        );

        $this->db->query("
            UPDATE `stories`
            SET `hotness` = :hotness
            WHERE `id` = :id
        ", [
            'hotness' => $hotness,
            'id' => $storyId,
        ]);
    }

    /**
     * Получить массив модификаторов hotness_mod для тегов истории.
     */
    private function getTagHotnessMods(int $storyId): array
    {
        $stmt = $this->db->query("
            SELECT COALESCE(t.hotness_mod, 0.0) as hotness_mod
            FROM `taggings` tg
            JOIN `tags` t ON tg.tag_id = t.id
            WHERE tg.story_id = :story_id
        ", ['story_id' => $storyId]);

        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'hotness_mod');
    }

    /**
     * Get all platform tags with description fields
     */
    public function getAllTags(): array
    {
        return $this->db->fetchAll("SELECT * FROM `tags` ORDER BY `slug` ASC");
    }

    /**
     * Получить комментарии для истории с фильтрацией игнорируемых и сортировкой
     */
    public function getCommentsForStory(int $storyId, array $mutedUserIds = []): array
    {
        $sql = "SELECT 
                    c.*,
                    u.username as author_name,
                    up.avatar as author_avatar,
                    CASE 
                        WHEN c.confidence_score > 0 THEN c.confidence_score
                        ELSE 0
                    END as calculated_confidence
                FROM comments c
                JOIN users u ON c.user_id = u.id
                LEFT JOIN user_profiles up ON u.id = up.user_id
                WHERE c.story_id = :story_id";
        
        $params = ['story_id' => $storyId];
        
        if (!empty($mutedUserIds)) {
            $placeholders = [];
            foreach ($mutedUserIds as $index => $mutedId) {
                $key = 'muted_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = (int)$mutedId;
            }
            $sql .= " AND c.user_id NOT IN (" . implode(',', $placeholders) . ")";
        }
        
        $sql .= " ORDER BY c.parent_id ASC, calculated_confidence DESC, c.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Fetch an array of only the tag IDs currently bound to a specific story
     */
    public function getStoryTagIds(int $storyId): array
    {
        $stmt = $this->db->query("SELECT `tag_id` FROM `taggings` WHERE `story_id` = :id", ['id' => $storyId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Atomically sync and bind tags to a story inside a secure database transaction.
     */
    public function syncTags(int $storyId, array $tagIds): bool
    {
        $tagIds = array_unique(array_map('intval', $tagIds));

        if (empty($tagIds)) {
            try {
                return $this->db->execute("DELETE FROM `taggings` WHERE `story_id` = ?", [$storyId]) > 0;
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->error("Failed to clear tags: " . $e->getMessage());
                }
                return false;
            }
        }

        try {
            $this->db->beginTransaction();

            $this->db->execute("DELETE FROM `taggings` WHERE `story_id` = ?", [$storyId]);

            $placeholders = [];
            $params = [];

            foreach ($tagIds as $tagId) {
                $placeholders[] = "(?, ?)";
                $params[] = $storyId;
                $params[] = (int)$tagId;
            }

            $sql = "INSERT INTO `taggings` (`story_id`, `tag_id`) VALUES " . implode(', ', $placeholders);
            $this->db->execute($sql, $params);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            if ($this->logger) {
                $this->logger->error("Failed to sync tags for story #{$storyId}: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Атомарно изменяет счётчик комментариев.
     */
    public function incrementCommentsCount(int $storyId, int $delta): void
    {
        $this->db->execute(
            "UPDATE stories SET comments_count = GREATEST(0, comments_count + ?) WHERE id = ?",
            [$delta, $storyId]
        );
    }

    /**
     * Пересчитывает счётчик комментариев с нуля (для синхронизации).
     * Полезно для админских скриптов или восстановления целостности данных.
     */
    public function recalculateCommentsCount(int $storyId): void
    {
        // Шаг 1: Получаем актуальное количество не удаленных комментариев
        // fetchColumn возвращает первую колонку первой строки результата (в данном случае число)
        $count = (int) $this->db->fetchColumn("
            SELECT COUNT(*) 
            FROM comments 
            WHERE story_id = ? AND deleted_at IS NULL
        ", [$storyId]);

        // Шаг 2: Обновляем поле в таблице stories простым и быстрым запросом по первичному ключу
        $this->db->execute("
            UPDATE stories 
            SET comments_count = ? 
            WHERE id = ?
        ", [$count, $storyId]);
    }
}