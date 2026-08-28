<?php

declare(strict_types=1);

namespace App\Modules\Votes\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

class Vote extends Model
{
    protected string $table = 'votes';

    protected array $fillable = [
        'user_id',
        'votable_type',
        'votable_id',
        'claps',
    ];

    private const ALLOWED_TYPES = [
        'story'   => 'stories',
        'comment' => 'comments',
    ];

    private const MAX_CLAPS = 50;

    public function getUserClaps(int $userId, string $type, int $id): int
    {
        if (!$this->isValidType($type)) {
            return 0;
        }

        $sql = "SELECT `claps`
                FROM `votes`
                WHERE `user_id` = :uid
                  AND `votable_type` = :type
                  AND `votable_id` = :id
                LIMIT 1";

        $val = $this->db->fetchColumn($sql, [
            'uid'  => $userId,
            'type' => $type,
            'id'   => $id,
        ]);

        return $val !== false && $val !== null ? (int)$val : 0;
    }

    public function addClap(int $userId, int $storyId): array
    {
        $targetTable = self::ALLOWED_TYPES['story'];

        try {
            $this->db->beginTransaction();

            $currentClaps = $this->getUserClaps($userId, 'story', $storyId);

            if ($currentClaps >= self::MAX_CLAPS) {
                $this->db->commit();
                return ['success' => false, 'message' => 'Максимум 50 хлопков.'];
            }

            if ($currentClaps === 0) {
                $this->db->execute(
                    "INSERT INTO `votes`
                     (`user_id`, `votable_type`, `votable_id`, `claps`)
                     VALUES (:uid, 'story', :id, 1)",
                    ['uid' => $userId, 'id' => $storyId]
                );
            } else {
                $this->db->execute(
                    "UPDATE `votes`
                     SET `claps` = `claps` + 1
                     WHERE `user_id` = :uid
                       AND `votable_type` = 'story'
                       AND `votable_id` = :id",
                    ['uid' => $userId, 'id' => $storyId]
                );
            }

            $this->db->execute(
                "UPDATE `{$targetTable}`
                 SET `score` = `score` + 1
                 WHERE `id` = :id",
                ['id' => $storyId]
            );

            $this->db->commit();

            $newClaps = $currentClaps + 1;
            $totalScore = $this->getScoreForEntity('story', $storyId);

            return ['success' => true, 'user_claps' => $newClaps, 'new_score' => $totalScore];

        } catch (\Exception $e) {
            $this->db->rollBack();
            if ($this->logger) {
                $this->logger->error('Ошибка транзакции хлопка', [
                    'user_id' => $userId,
                    'story_id' => $storyId,
                    'exception' => $e->getMessage(),
                ]);
            }
            return ['success' => false, 'message' => 'Ошибка обработки хлопка.'];
        }
    }

    /**
     * Лайк комментария (0/1). Повторный клик — снять лайк.
     */
    public function toggleCommentLike(int $userId, int $commentId): array
    {
        try {
            $this->db->beginTransaction();

            $current = $this->getUserClaps($userId, 'comment', $commentId);
            $liked = $current > 0;

            if ($liked) {
                $this->db->execute(
                    "DELETE FROM `votes`
                     WHERE `user_id` = :uid
                       AND `votable_type` = 'comment'
                       AND `votable_id` = :id",
                    ['uid' => $userId, 'id' => $commentId]
                );
            } else {
                $this->db->execute(
                    "INSERT INTO `votes`
                     (`user_id`, `votable_type`, `votable_id`, `claps`)
                     VALUES (:uid, 'comment', :id, 1)",
                    ['uid' => $userId, 'id' => $commentId]
                );
            }

            $delta = $liked ? -1 : 1;

            $this->db->execute(
                "UPDATE `comments`
                 SET `score` = `score` + :delta
                 WHERE `id` = :id",
                ['delta' => $delta, 'id' => $commentId]
            );

            $this->db->commit();

            return [
                'success' => true,
                'liked' => !$liked,
                'new_score' => $this->getScoreForEntity('comment', $commentId),
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            if ($this->logger) {
                $this->logger->error('Ошибка транзакции лайка комментария', [
                    'user_id' => $userId,
                    'comment_id' => $commentId,
                    'exception' => $e->getMessage(),
                ]);
            }
            return ['success' => false, 'message' => 'Ошибка обработки лайка.'];
        }
    }

    public function getScoreForEntity(string $type, int $id): int
    {
        if (!$this->isValidType($type)) return 0;
        $targetTable = self::ALLOWED_TYPES[$type];
        $sql = "SELECT `score` FROM `{$targetTable}` WHERE `id` = :id LIMIT 1";
        $score = $this->db->fetchColumn($sql, ['id' => $id]);
        return $score !== false && $score !== null ? (int)$score : 0;
    }

    public function getOwnerUserId(string $type, int $id): ?int
    {
        if (!$this->isValidType($type)) return null;
        $targetTable = self::ALLOWED_TYPES[$type];
        $sql = "SELECT `user_id` FROM `{$targetTable}` WHERE `id` = :id LIMIT 1";
        $userId = $this->db->fetchColumn($sql, ['id' => $id]);
        return $userId !== false && $userId !== null ? (int)$userId : null;
    }

    public function getUserClapsForComments(int $userId, array $commentIds): array
    {
        if (empty($commentIds)) return [];
        $placeholders = [];
        $params = ['user_id' => $userId];
        foreach ($commentIds as $index => $id) {
            $key = 'cid_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }
        $sql = "SELECT votable_id, claps
                FROM {$this->table}
                WHERE user_id = :user_id
                  AND votable_id IN (" . implode(',', $placeholders) . ")
                  AND votable_type = 'comment'";
        $rows = $this->db->fetchAll($sql, $params);
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['votable_id']] = (int)$row['claps'];
        }
        return $result;
    }

    public function getUserClapsForStories(int $userId, array $storyIds): array
    {
        if (empty($storyIds)) return [];
        $placeholders = [];
        $params = ['user_id' => $userId];
        foreach ($storyIds as $index => $id) {
            $key = 'sid_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }
        $sql = "SELECT votable_id, claps
                FROM {$this->table}
                WHERE user_id = :user_id
                  AND votable_id IN (" . implode(',', $placeholders) . ")
                  AND votable_type = 'story'";
        $rows = $this->db->fetchAll($sql, $params);
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['votable_id']] = (int)$row['claps'];
        }
        return $result;
    }

    private function isValidType(string $type): bool
    {
        return isset(self::ALLOWED_TYPES[$type]);
    }
}