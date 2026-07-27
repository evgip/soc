<?php

declare(strict_types=1);

namespace App\Modules\Votes\Services;

use App\Modules\Votes\Models\Vote;
use App\Modules\Users\Models\User;
use App\Modules\Comments\Models\Comment;
use App\Modules\Stories\Services\RankingService; 
use W3a\Core\Logger;
use W3a\Core\Database;


/**
 * Сервис голосования.
 */
class VoteService
{
    private Vote $voteModel;
    private User $userModel;
    private Comment $commentModel;
    private Logger $logger;
    private Database $db;
    private RankingService $rankingService;
    
    private const DEFAULT_MIN_KARMA = 10;

    public function __construct(
        Vote $voteModel, 
        User $userModel, 
        Comment $commentModel,
        Logger $logger,
        Database $db,
        RankingService $rankingService
    ) {
        $this->voteModel = $voteModel;
        $this->userModel = $userModel;
        $this->commentModel = $commentModel;
        $this->logger = $logger;
        $this->db = $db;
        $this->rankingService = $rankingService;
    }

	public function canUserDownvote(int $userId): bool
	{
		if ($userId <= 0) {
			return false;
		}
		$user = $this->userModel->find($userId);
		if (empty($user)) {
			return false;
		}
		if ($this->isUserAdmin($user)) {
			return true;
		}
		return $this->userModel->getUserKarma($userId) >= $this->getMinKarmaForDownvote();
	}
	public function getMinKarmaForDownvote(): int
	{
		return $this->getMinKarmaForDownvoteInternal();
	}

    public function handleVote(int $userId, string $type, int $targetId, int $voteValue): array
    {
        $ownerCheck = $this->checkSelfVote($userId, $type, $targetId);
        if (!$ownerCheck['allowed']) {
            return $ownerCheck;
        }

       // if ($voteValue === -1 && !$this->canDownvote($userId)) {
         //   $minKarma = $this->getMinKarma();
			
		if ($voteValue === -1 && !$this->canUserDownvote($userId)) {
			$minKarma = $this->getMinKarmaForDownvote();	
			
            $userKarma = $this->userModel->getUserKarma($userId);
            return [
                'success' => false,
                'message' => "Дизлайки доступны от {$minKarma} кармы. У вас: {$userKarma}.",
            ];
        }

        if (!$this->voteModel->toggleVote($userId, $type, $targetId, $voteValue)) {
            return [
                'success' => false,
                'message' => 'Ошибка обработки голоса.',
            ];
        }

        if ($type === 'comment') {
            $this->updateCommentConfidenceScore($targetId);
        }

        if ($type === 'story') {
            $this->updateStoryHotness($targetId);
        }

        return ['success' => true, 'message' => 'Голос учтён.'];
    }

	private function getMinKarmaForDownvoteInternal(): int
	{
		return (int) config('constants.votes.min_karma_for_downvote', self::DEFAULT_MIN_KARMA, 'int');
	}

    public function getNewScore(string $type, int $targetId): int
    {
        return $this->voteModel->getScoreForEntity($type, $targetId);
    }

    public function getUserVote(int $userId, string $type, int $targetId): ?int
    {
        return $this->voteModel->getUserVote($userId, $type, $targetId);
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

    private function checkSelfVote(int $userId, string $type, int $targetId): array
    {
        $ownerId = $this->voteModel->getOwnerUserId($type, $targetId);
        
        if ($ownerId === null) {
            return [
                'allowed' => false,
                'success' => false,
                'message' => 'Контент не найден.',
            ];
        }
        
        if ($ownerId === $userId) {
            return [
                'allowed' => false,
                'success' => false,
                'message' => 'Вы не можете голосовать за свой собственный контент.',
            ];
        }
        
        return ['allowed' => true, 'success' => true, 'message' => ''];
    }

    private function isUserAdmin(array $user): bool
    {
        return (isset($user['role']) && $user['role'] === 'admin')
            || (isset($user['is_admin']) && (int)$user['is_admin'] === 1);
    }

    private function getMinKarma(): int
    {
        return (int)(config('app.min_karma_for_downvote') ?? self::DEFAULT_MIN_KARMA);
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