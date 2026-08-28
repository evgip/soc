<?php

declare(strict_types=1);

namespace App\Modules\Votes\Services;

use App\Modules\Votes\Models\Vote;
use App\Modules\Comments\Models\Comment;
use App\Modules\Stories\Services\RankingService;
use W3a\Core\Support\Logger;
use W3a\Core\Database\Database;

class VoteService
{
    private Vote $voteModel;
    private Comment $commentModel;
    private Logger $logger;
    private Database $db;
    private RankingService $rankingService;

    public function __construct(
        Vote $voteModel,
        Comment $commentModel,
        Logger $logger,
        Database $db,
        RankingService $rankingService
    ) {
        $this->voteModel = $voteModel;
        $this->commentModel = $commentModel;
        $this->logger = $logger;
        $this->db = $db;
        $this->rankingService = $rankingService;
    }

    public function handleClap(int $userId, int $storyId): array
    {
        $result = $this->voteModel->addClap($userId, $storyId);

        if ($result['success']) {
            $this->updateStoryHotness($storyId);
        }

        return $result;
    }

    public function handleCommentLike(int $userId, int $commentId): array
    {
        $result = $this->voteModel->toggleCommentLike($userId, $commentId);

        if ($result['success']) {
            $this->updateCommentConfidenceScore($commentId);
        }

        return $result;
    }

    public function getNewScore(string $type, int $targetId): int
    {
        return $this->voteModel->getScoreForEntity($type, $targetId);
    }

    public function getUserClaps(int $userId, string $type, int $targetId): int
    {
        return $this->voteModel->getUserClaps($userId, $type, $targetId);
    }

    private function updateCommentConfidenceScore(int $commentId): void
    {
        try {
            $comment = $this->commentModel->getCommentById($commentId);
            if ($comment) {
                $confidenceScore = $this->rankingService->wilsonScore(
                    (int)$comment['score'],
                    (int)$comment['flag_count']
                );
                $this->commentModel->updateConfidenceScore($commentId, $confidenceScore);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to update confidence score for comment', [
                'comment_id' => $commentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function updateStoryHotness(int $storyId): void
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    s.`score`,
                    s.`created_at`,
                    COALESCE(SUM(t.`hotness_mod`), 0.0) AS `tag_hotness_mod`
                FROM `stories` s
                LEFT JOIN `taggings` tg ON s.`id` = tg.`story_id`
                LEFT JOIN `tags` t ON tg.`tag_id` = t.`id`
                WHERE s.`id` = :id
                GROUP BY s.`id`
            ");
            $stmt->execute(['id' => $storyId]);
            $story = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($story) {
                $tagMods = [(float)$story['tag_hotness_mod']];
                $hotness = $this->rankingService->calculateHotness(
                    (int)$story['score'],
                    $story['created_at'],
                    $tagMods
                );
                $update = $this->db->prepare("
                    UPDATE `stories` SET `hotness` = :h WHERE `id` = :id
                ");
                $update->execute(['h' => $hotness, 'id' => $storyId]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to update story hotness', [
                'story_id' => $storyId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}