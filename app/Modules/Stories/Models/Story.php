<?php

declare(strict_types=1);

namespace App\Modules\Stories\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;
use W3a\Core\Support\HtmlSanitizer;
use App\Modules\Stories\Services\RankingService; 

class Story extends Model
{
    protected string $table = 'stories';
	
	public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SCHEDULED = 'scheduled';
    
    private RankingService $rankingService;
    private HtmlSanitizer $sanitizer;

    protected array $fillable = [
        'user_id',
        'title',
        'description_text',
        'description_json',
        'score',
        'comments_count',
        'user_is_following',
        'is_staff_pick',      // Staff Picks
        'picked_at',          // Staff Picks
        'has_paywall',        // Paywall: есть ли закрытая часть
        'paywall_type',    // Paywall: none / members / subscribers
		'status', 		 
        'deleted_at'
    ];

    public function __construct(
        Database $db, 
        Logger $logger, 
        ?RankingService $rankingService = null,
        ?HtmlSanitizer $sanitizer = null
    ) {
        parent::__construct($db, $logger);
        $this->rankingService = $rankingService ?? new RankingService();
        $this->sanitizer = $sanitizer ?? new HtmlSanitizer();
    }

    // =========================================================================
    // PAYWALL
    // =========================================================================

    /**
     * Обновляет флаги paywall на основе содержимого description_json.
     * 
     * Читает JSON статьи из БД, ищет блок типа 'paywall' и устанавливает:
     * - has_paywall = 1 или 0
     * - paywall_type = 'members' (если есть paywall) или 'none'
     * 
     * @param int $storyId ID статьи
     * @param string $paywallType Тип доступа (по умолчанию 'members', можно передать 'subscribers')
     * @return bool Успешно ли обновлено
     */
    public function updatePaywallFlags(int $storyId, string $paywallType = 'members'): bool
    {
        $json = $this->db->fetchColumn(
            "SELECT `description_json` FROM `stories` WHERE `id` = ?",
            [$storyId]
        );

        if (empty($json)) {
            return false;
        }

        // Проверяем наличие блока типа 'paywall' в JSON
        $hasPaywall = str_contains($json, '"type":"paywall"')
                   || str_contains($json, '"type": "paywall"');

        $finalType = $hasPaywall ? $paywallType : 'none';

        return $this->db->execute(
            "UPDATE `stories` 
             SET `has_paywall` = ?, `paywall_type` = ? 
             WHERE `id` = ?",
            [(int)$hasPaywall, $finalType, $storyId]
        ) > 0;
    }

    /**
     * Получить список ID статей с paywall (для фильтрации в ленте).
     */
    public function getPaywallStoryIds(array $storyIds): array
    {
        if (empty($storyIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($storyIds), '?'));
        $stmt = $this->db->query(
            "SELECT `id` FROM `stories` 
             WHERE `id` IN ($placeholders) AND `has_paywall` = 1",
            $storyIds
        );

        return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    // =========================================================================
    // МЕТОДЫ ИЗВЛЕЧЕНИЯ ДАННЫХ ИЗ JSON
    // =========================================================================

    /**
     * Извлекает заголовок из первого H1/H2 блока Editor.js
     */
    public function extractTitleFromJson(string $json): string
    {
        $data = json_decode($json, true);
        if (!$data || !isset($data['blocks'])) {
            return '';
        }

        foreach ($data['blocks'] as $block) {
            if ($block['type'] === 'header') {
                $level = (int)($block['data']['level'] ?? 2);
                if ($level <= 2) {
                    return strip_tags($block['data']['text'] ?? '');
                }
            }
        }

        return '';
    }

    /**
     * Принимает JSON от Editor.js, очищает HTML и извлекает текст для поиска.
     */
    public function processEditorJsData(string $json): array
    {
        $data = json_decode($json, true);
        if (!$data || !isset($data['blocks'])) {
            return ['description_json' => $json, 'description_text' => ''];
        }

        $plainTextParts = [];

        foreach ($data['blocks'] as &$block) {
            $type = $block['type'] ?? '';
            $d = $block['data'] ?? [];

            if (in_array($type, ['paragraph', 'header', 'quote'], true)) {
                $text = $d['text'] ?? '';
                $block['data']['text'] = $this->sanitizer->clean($text);
                $plainTextParts[] = strip_tags($block['data']['text']);
                
            } elseif ($type === 'list') {
                foreach ($d['items'] ?? [] as &$item) {
                    $cleanItem = $this->sanitizer->clean($item['content'] ?? '');
                    $item['content'] = $cleanItem;
                    $plainTextParts[] = strip_tags($cleanItem);
                }
                
            } elseif ($type === 'code') {
                $plainTextParts[] = htmlspecialchars($d['code'] ?? '', ENT_QUOTES, 'UTF-8');
            }
            // paywall-блок пропускаем — он не содержит текста для поиска
        }

        $safeJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $plainText = trim(implode("\n\n", $plainTextParts));

        return [
            'description_json' => $safeJson,
            'description_text' => $plainText,
        ];
    }


    // =========================================================================
    // МЕТОДЫ ЛЕНТЫ И ФИЛЬТРАЦИИ (Domain полностью удален)
    // =========================================================================

	private function buildFeedConditions(
		string $tagslug = '', array $excludeTagIds = [],
		string $author = '', array $mutedUserIds = [], bool $showDeleted = false
	): array {
		$where = [];
		$bindings = [];

		// Всегда показываем только опубликованные (черновики не в ленте)
		$where[] = "s.status = :status";
		$bindings[':status'] = self::STATUS_PUBLISHED;

		// Удалённые — только админам
		if (!$showDeleted) {
			$where[] = "s.deleted_at IS NULL";
		}
		
		if ($tagslug !== '') {
			$where[] = "t.slug = :slug";
			$bindings[':slug'] = $tagslug;
		}
		if ($author !== '') {
			$where[] = "u.username = :author";
			$bindings[':author'] = $author;
		}

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
        array $excludeTagIds = [], string $sort = 'hot',
        string $author = '', array $mutedUserIds = []
    ): array {
        $repo = new \App\Modules\Stories\Repositories\StoryRepository($this->db);
        
        $conditions = $this->buildFeedConditions($tagslug, $excludeTagIds, $author, $mutedUserIds, $showDeleted);

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
        string $tagslug = '', array $excludeTagIds = [],
        string $author = '', array $mutedUserIds = []
    ): int {
        $repo = new \App\Modules\Stories\Repositories\StoryRepository($this->db);
        
        if ($tagslug !== '' || !empty($excludeTagIds)) {
            $repo->withTags(); 
        }
        
        $conditions = $this->buildFeedConditions($tagslug, $excludeTagIds, $author, $mutedUserIds);

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
			$repo->addWhere('s.status = :status', ['status' => self::STATUS_PUBLISHED]);
		}
		
		return $repo->first();
	}
	
	/**
	 * Получить статью для автора (включая его черновики)
	 * Используется когда автор просматривает свою статью
	 */
	public function getForAuthor(int $id, int $authorId): ?array {
		$repo = new \App\Modules\Stories\Repositories\StoryRepository($this->db);
		
		$repo->withAuthor()->withAvatar()->withTags()
			 ->addWhere('s.id = :id', ['id' => $id])
			 ->addWhere('s.user_id = :author_id', ['author_id' => $authorId])
			 ->addWhere('s.deleted_at IS NULL');
		
		return $repo->first();
	}
		
    public function recalculateHotness(int $storyId): void
    {
        $story = $this->find($storyId);
        if (!$story) return;

        $tagMods = $this->getTagHotnessMods($storyId);

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

    public function getAllTags(): array
    {
        return $this->db->fetchAll("SELECT * FROM `tags` ORDER BY `slug` ASC");
    }

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

    public function getStoryTagIds(int $storyId): array
    {
        $stmt = $this->db->query("SELECT `tag_id` FROM `taggings` WHERE `story_id` = :id", ['id' => $storyId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

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

    public function incrementCommentsCount(int $storyId, int $delta): void
    {
        $this->db->execute(
            "UPDATE stories SET comments_count = GREATEST(0, comments_count + ?) WHERE id = ?",
            [$delta, $storyId]
        );
    }

    public function recalculateCommentsCount(int $storyId): void
    {
        $count = (int) $this->db->fetchColumn("
            SELECT COUNT(*) 
            FROM comments 
            WHERE story_id = ? AND deleted_at IS NULL
        ", [$storyId]);

        $this->db->execute("
            UPDATE stories 
            SET comments_count = ? 
            WHERE id = ?
        ", [$count, $storyId]);
    }

    public function getSubscribedFeed(
        int $userId, array $followedUserIds, array $followedTagIds,
        int $limit, int $offset, string $sort = 'new', array $mutedUserIds = []
    ): array {
        $repo = new \App\Modules\Stories\Repositories\StoryRepository($this->db);
        
        $repo->fromSubscribed($userId, $followedUserIds, $followedTagIds)
             ->withAuthor()
             ->withAvatar()
             ->withTags()
             ->addWhere('s.deleted_at IS NULL')
			 ->addWhere('s.status = :status', ['status' => self::STATUS_PUBLISHED]);

        if (!empty($mutedUserIds)) {
            $inData = $this->db->buildInClause($mutedUserIds, 'muted_user');
            $repo->addWhere("s.user_id NOT IN ({$inData['clause']})", $inData['bindings']);
        }

        $orderBy = match ($sort) {
            'top' => 's.score DESC, s.created_at DESC',
            'hot' => 's.hotness DESC',
            default => 's.created_at DESC',
        };

        return $repo->setOrderBy($orderBy)
                    ->paginate($limit, $offset)
                    ->get();
    }

    public function getSubscribedTotalCount(
        int $userId, array $followedUserIds, array $followedTagIds, array $mutedUserIds = []
    ): int {
        $repo = new \App\Modules\Stories\Repositories\StoryRepository($this->db);
        
        $repo->fromSubscribed($userId, $followedUserIds, $followedTagIds)
             ->withAuthor()
             ->addWhere('s.deleted_at IS NULL')
			 ->addWhere('s.status = :status', ['status' => self::STATUS_PUBLISHED]);

        if (!empty($mutedUserIds)) {
            $inData = $this->db->buildInClause($mutedUserIds, 'muted_user');
            $repo->addWhere("s.user_id NOT IN ({$inData['clause']})", $inData['bindings']);
        }

        return $repo->count();
    }

    public function countNewSubscribed(int $userId, array $followedUserIds, array $followedTagIds): int
    {
        if (empty($followedUserIds) && empty($followedTagIds)) {
            return 0;
        }

        $repo = new \App\Modules\Stories\Repositories\StoryRepository($this->db);
        
		$repo->fromSubscribed($userId, $followedUserIds, $followedTagIds)
			 ->addWhere('s.deleted_at IS NULL')
			 ->addWhere('s.status = :status', ['status' => self::STATUS_PUBLISHED])
			 ->addWhere('s.created_at >= :since', [
				 'since' => date('Y-m-d H:i:s', strtotime('-24 hours'))
			 ]);
        
        return $repo->count();
    }
	
    // ============================================================
    // МЕТОДЫ ДЛЯ ЧЕРНОВИКОВ (добавить в конец класса)
    // ============================================================

    /**
     * Получить черновики пользователя
     */
    public function getUserDrafts(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT id, title, description_text, cover_image, 
                       word_count, reading_time, updated_at, draft_version
                FROM stories
                WHERE user_id = :user_id 
                AND status = 'draft' 
                AND deleted_at IS NULL
                ORDER BY updated_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Подсчитать черновики пользователя
     */
    public function countUserDrafts(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM stories 
                WHERE user_id = :user_id 
                AND status = 'draft' 
                AND deleted_at IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return (int)$stmt->fetchColumn();
    }

	/**
     * Получить статьи пользователя по статусу с пагинацией.
     * Статус проверяется по белому списку (draft / published / scheduled).
     */
    public function getStoriesByStatus(int $userId, string $status, int $page = 1, int $perPage = 20): array
    {
        $allowed = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_SCHEDULED];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException("Недопустимый статус статьи: {$status}");
        }

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT id, title, description_text, cover_image,
                       word_count, reading_time, created_at, updated_at, draft_version, slug, comments_count
                FROM stories
                WHERE user_id = :user_id
                AND status = :status
                AND deleted_at IS NULL
                ORDER BY updated_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue('status', $status, \PDO::PARAM_STR);
        $stmt->bindValue('limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Подсчитать статьи пользователя по статусу.
     */
    public function countStoriesByStatus(int $userId, string $status): int
    {
        $allowed = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_SCHEDULED];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException("Недопустимый статус статьи: {$status}");
        }

        $sql = "SELECT COUNT(*) FROM stories
                WHERE user_id = :user_id
                AND status = :status
                AND deleted_at IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue('status', $status, \PDO::PARAM_STR);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

	/**
	 * Получить все опубликованные статьи автора.
	 * Используется в модуле Collections для добавления статей в коллекции.
	 */
	public function getPublishedByAuthor(int $userId): array
	{
		$sql = "SELECT id, title, slug, created_at, comments_count
				FROM stories
				WHERE user_id = :user_id
				  AND status = 'published'
				  AND deleted_at IS NULL
				ORDER BY created_at DESC";

		return $this->db->fetchAll($sql, ['user_id' => $userId]);
	}

    /**
     * Генерация slug (приватный метод)
     */
    private function generateSlug(string $title, int $id): string
    {
        $translit = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];

        $slug = mb_strtolower($title);
        $slug = strtr($slug, $translit);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            $slug = 'post-' . $id;
        }

        return $slug;
    }
}