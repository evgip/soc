<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use W3a\Core\Database\Database;

class TagAttachmentService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function attach(array $stories): array
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