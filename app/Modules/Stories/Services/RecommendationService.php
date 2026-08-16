<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use W3a\Core\Foundation\Container;
use W3a\Core\Database\Database;
use App\Modules\Stories\Models\StoryView;
use App\Modules\Subscriptions\Services\SubscriptionService;
use App\Modules\Tags\Models\TagFilter;

class RecommendationService
{
    private Database $db;
    private StoryView $storyView;
    private SubscriptionService $subscriptionService;
    private TagFilter $tagFilter;

    public function __construct(
        Database $db,
        StoryView $storyView,
        SubscriptionService $subscriptionService,
        TagFilter $tagFilter
    ) {
        $this->db = $db;
        $this->storyView = $storyView;
        $this->subscriptionService = $subscriptionService;
        $this->tagFilter = $tagFilter;
    }

    public function getForYouFeed(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) {
            return $this->getPopularFallback($limit);
        }

        $followedUserIds = $this->subscriptionService->getFollowedUserIds($userId);
        $followedTagIds = $this->subscriptionService->getFollowedTagIds($userId);
        $topTags = $this->storyView->getUserTopTags($userId, 5);
        $viewedStoryIds = $this->storyView->getViewedStoryIds($userId, 50);
        $excludedTagIds = $this->tagFilter->getFilteredTagIds($userId);

        if (empty($followedUserIds) && empty($followedTagIds) && empty($topTags)) {
            return $this->getPopularFallback($limit, [], $excludedTagIds);
        }

        $readTagIds = array_column($topTags, 'tag_id');
        $allTagIds = array_unique(array_merge($followedTagIds, $readTagIds));

        $stories = $this->getRecommendedStories(
            $followedUserIds,
            $allTagIds,
            $viewedStoryIds,
            $limit,
            $excludedTagIds
        );

        if (count($stories) < $limit) {
            $excludeIds = array_column($stories, 'id');
            $popular = $this->getPopularFallback($limit - count($stories), $excludeIds, $excludedTagIds);
            $stories = array_merge($stories, $popular);
        }

        return $stories;
    }

    private function getRecommendedStories(
        array $followedUserIds,
        array $tagIds,
        array $excludeStoryIds,
        int $limit,
        array $excludedTagIds = []
    ): array {
        $where = ['s.deleted_at IS NULL'];
        $bindings = [];

        if (!empty($excludeStoryIds)) {
            $excludeStoryIds = array_map('intval', $excludeStoryIds);
            $placeholders = implode(',', array_fill(0, count($excludeStoryIds), '?'));
            $where[] = "s.id NOT IN ($placeholders)";
            $bindings = array_merge($bindings, $excludeStoryIds);
        }

        // Не показываем истории, содержащие отфильтрованные (скрытые) теги
        if (!empty($excludedTagIds)) {
            $excludedTagIds = array_map('intval', $excludedTagIds);
            $placeholders = implode(',', array_fill(0, count($excludedTagIds), '?'));
            $where[] = "NOT EXISTS (
                SELECT 1 FROM taggings tg_ex
                WHERE tg_ex.story_id = s.id AND tg_ex.tag_id IN ($placeholders)
            )";
            $bindings = array_merge($bindings, $excludedTagIds);
        }

        $interestConditions = [];

        if (!empty($followedUserIds)) {
            $userPlaceholders = implode(',', array_fill(0, count($followedUserIds), '?'));
            $interestConditions[] = "s.user_id IN ($userPlaceholders)";
            $bindings = array_merge($bindings, $followedUserIds);
        }

        if (!empty($tagIds)) {
            $tagPlaceholders = implode(',', array_fill(0, count($tagIds), '?'));
            $interestConditions[] = "EXISTS (
                SELECT 1 FROM taggings tg 
                WHERE tg.story_id = s.id AND tg.tag_id IN ($tagPlaceholders)
            )";
            $bindings = array_merge($bindings, $tagIds);
        }

        if (!empty($interestConditions)) {
            $where[] = '(' . implode(' OR ', $interestConditions) . ')';
        }

        $whereClause = implode(' AND ', $where);
        $bindings[] = $limit;

        $sql = "
            SELECT 
                s.*,
                u.username as author_name,
                up.avatar as author_avatar
            FROM `stories` s
            JOIN `users` u ON s.user_id = u.id
            LEFT JOIN `user_profiles` up ON u.id = up.user_id
            WHERE $whereClause
            ORDER BY s.hotness DESC, s.created_at DESC
            LIMIT ?
        ";

        $stmt = $this->db->query($sql, $bindings);
        $stories = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return $this->attachTags($stories);
    }

    private function getPopularFallback(int $limit, array $excludeIds = [], array $excludedTagIds = []): array
    {
        $where = [
            's.deleted_at IS NULL',
            's.created_at >= NOW() - INTERVAL 7 DAY'
        ];
        $bindings = [];

        if (!empty($excludeIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $where[] = "s.id NOT IN ($placeholders)";
            $bindings = array_merge($bindings, $excludeIds);
        }

        // Не показываем истории, содержащие отфильтрованные (скрытые) теги
        if (!empty($excludedTagIds)) {
            $excludedTagIds = array_map('intval', $excludedTagIds);
            $placeholders = implode(',', array_fill(0, count($excludedTagIds), '?'));
            $where[] = "NOT EXISTS (
                SELECT 1 FROM taggings tg_ex
                WHERE tg_ex.story_id = s.id AND tg_ex.tag_id IN ($placeholders)
            )";
            $bindings = array_merge($bindings, $excludedTagIds);
        }

        $whereClause = implode(' AND ', $where);
        $bindings[] = $limit;

        $sql = "
            SELECT 
                s.*,
                u.username as author_name,
                up.avatar as author_avatar
            FROM `stories` s
            JOIN `users` u ON s.user_id = u.id
            LEFT JOIN `user_profiles` up ON u.id = up.user_id
            WHERE $whereClause
            ORDER BY s.hotness DESC
            LIMIT ?
        ";

        $stmt = $this->db->query($sql, $bindings);
        $stories = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return $this->attachTags($stories);
    }

    private function attachTags(array $stories): array
    {
        if (empty($stories)) {
            return [];
        }

        $storyIds = array_column($stories, 'id');
        $placeholders = implode(',', array_fill(0, count($storyIds), '?'));

        $sql = "
            SELECT 
                tg.story_id,
                t.slug,
                t.name
            FROM `taggings` tg
            JOIN `tags` t ON tg.tag_id = t.id
            WHERE tg.story_id IN ($placeholders)
            ORDER BY t.slug ASC
        ";

        $stmt = $this->db->query($sql, $storyIds);
        $tagsData = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $tagsByStory = [];
        foreach ($tagsData as $tag) {
            $storyId = (int)$tag['story_id'];
            $tagsByStory[$storyId][] = [
                'slug' => $tag['slug'],
                'name' => $tag['name'],
            ];
        }

        foreach ($stories as &$story) {
            $storyId = (int)$story['id'];
            $story['tags_with_names'] = $tagsByStory[$storyId] ?? [];
        }

        return $stories;
    }
}