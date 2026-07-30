<?php

declare(strict_types=1);

namespace App\Modules\Votes\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

/**
 * Модель для работы с голосами пользователей.
 * 
 * Поддерживает полиморфное голосование за истории и комментарии.
 * Использует транзакции для обеспечения целостности данных.
 */
class Vote extends Model
{
    protected string $table = 'votes';

    /**
     * Поля, доступные для массового присваивания.
     * Должны соответствовать реальной структуре таблицы votes:
     * - user_id: ID проголосовавшего пользователя
     * - votable_type: тип сущности ('story' или 'comment')
     * - votable_id: ID сущности
     * - vote_type: направление голоса (1 или -1)
     */
    protected array $fillable = [
        'user_id',
        'votable_type',
        'votable_id',
        'vote_type',
    ];

    /**
     * Whitelist разрешённых типов сущностей и соответствующих таблиц БД.
     * Защита от SQL-инъекций через имя таблицы.
     */
    private const ALLOWED_TYPES = [
        'story'   => 'stories',
        'comment' => 'comments',
    ];

    /**
     * Получить текущий голос пользователя за сущность.
     * 
     * @param int    $userId ID пользователя
     * @param string $type   Тип сущности ('story' или 'comment')
     * @param int    $id     ID сущности
     * @return int|null 1 (upvote), -1 (downvote) или null (нет голоса)
     */
    public function getUserVote(int $userId, string $type, int $id): ?int
    {
        if (!$this->isValidType($type)) {
            return null;
        }

        $sql = "SELECT `vote_type` 
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

        return $val !== false && $val !== null ? (int)$val : null;
    }

    /**
     * Переключить голос пользователя (создать/изменить/удалить).
     * 
     * Логика:
     * - Если голос совпадает с текущим → удалить голос (отмена)
     * - Если голос отличается → обновить направление
     * - Если голоса нет → создать новый
     * 
     * @param int    $userId    ID пользователя
     * @param string $type      Тип сущности ('story' или 'comment')
     * @param int    $id        ID сущности
     * @param int    $voteValue Направление (1 или -1)
     * @return bool true при успехе, false при ошибке
     */
    public function toggleVote(int $userId, string $type, int $id, int $voteValue): bool
    {
        // Валидация направления голоса
        if ($voteValue !== 1 && $voteValue !== -1) {
            if ($this->logger) {
                $this->logger->warning('Недопустимое значение голоса', [
                    'user_id' => $userId,
                    'vote_value' => $voteValue,
                ]);
            }
            return false;
        }

        // Проверка типа сущности (whitelist)
        if (!$this->isValidType($type)) {
            if ($this->logger) {
                $this->logger->warning('Попытка голосования за недопустимый тип', [
                    'user_id' => $userId,
                    'type' => $type,
                    'target_id' => $id,
                ]);
            }
            return false;
        }

        $targetTable = self::ALLOWED_TYPES[$type];

        try {
            $this->db->beginTransaction();

            $existingVote = $this->getUserVote($userId, $type, $id);
            $scoreDelta = 0;

            if ($existingVote === $voteValue) {
                // CASE 1: Повторный клик на активную стрелку → отмена голоса
                $this->db->execute(
                    "DELETE FROM `votes` 
                     WHERE `user_id` = :uid 
                       AND `votable_type` = :type 
                       AND `votable_id` = :id",
                    [
                        'uid'  => $userId,
                        'type' => $type,
                        'id'   => $id,
                    ]
                );
                
                // Инвертируем исходное значение
                $scoreDelta = -$voteValue;
                
            } else {
                if ($existingVote !== null) {
                    // CASE 2: Смена направления голоса (например, с up на down)
                    $this->db->execute(
                        "UPDATE `votes` 
                         SET `vote_type` = :vval 
                         WHERE `user_id` = :uid 
                           AND `votable_type` = :type 
                           AND `votable_id` = :id",
                        [
                            'vval' => $voteValue,
                            'uid'  => $userId,
                            'type' => $type,
                            'id'   => $id,
                        ]
                    );
                    
                    // Двойная дельта (например, с +1 на -1 = -2)
                    $scoreDelta = $voteValue * 2;
                    
                } else {
                    // CASE 3: Новый голос
                    $this->db->execute(
                        "INSERT INTO `votes` 
                         (`user_id`, `votable_type`, `votable_id`, `vote_type`) 
                         VALUES (:uid, :type, :id, :vval)",
                        [
                            'uid'  => $userId,
                            'type' => $type,
                            'id'   => $id,
                            'vval' => $voteValue,
                        ]
                    );
                    
                    $scoreDelta = $voteValue;
                }
            }

            // Атомарное обновление score в целевой таблице
            if ($scoreDelta !== 0) {
                // Используем прямой PDO для bindValue с типом INT
                $pdo = $this->db->pdo();
                $stmt = $pdo->prepare(
                    "UPDATE `{$targetTable}` 
                     SET `score` = `score` + :delta 
                     WHERE `id` = :id"
                );
                $stmt->bindValue(':delta', $scoreDelta, \PDO::PARAM_INT);
                $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
                $stmt->execute();
            }

            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            if ($this->logger) {
                $this->logger->error('Ошибка транзакции голосования', [
                    'user_id' => $userId,
                    'type' => $type,
                    'target_id' => $id,
                    'vote_value' => $voteValue,
                    'exception' => $e->getMessage(),
                ]);
            }
            return false;
        }
    }

    /**
     * Получить текущий score сущности.
     * 
     * @param string $type Тип сущности ('story' или 'comment')
     * @param int    $id   ID сущности
     * @return int Текущий score (0, если сущность не найдена)
     */
    public function getScoreForEntity(string $type, int $id): int
    {
        if (!$this->isValidType($type)) {
            return 0;
        }

        $targetTable = self::ALLOWED_TYPES[$type];
        
        $sql = "SELECT `score` 
                FROM `{$targetTable}` 
                WHERE `id` = :id 
                LIMIT 1";

        $score = $this->db->fetchColumn($sql, ['id' => $id]);
        return $score !== false && $score !== null ? (int)$score : 0;
    }

    /**
     * Проверить, является ли тип сущности допустимым.
     * 
     * @param string $type Тип сущности
     * @return bool true, если тип разрешён
     */
    private function isValidType(string $type): bool
    {
        return isset(self::ALLOWED_TYPES[$type]);
    }
    
    /**
     * Получить ID автора контента.
     * 
     * @param string $type Тип сущности ('story' или 'comment')
     * @param int    $id   ID сущности
     * @return int|null ID автора или null, если контент не найден
     */
    public function getOwnerUserId(string $type, int $id): ?int
    {
        if (!$this->isValidType($type)) {
            return null;
        }

        $targetTable = self::ALLOWED_TYPES[$type];
        
        $sql = "SELECT `user_id` 
                FROM `{$targetTable}` 
                WHERE `id` = :id 
                LIMIT 1";

        $userId = $this->db->fetchColumn($sql, ['id' => $id]);
        return $userId !== false && $userId !== null ? (int)$userId : null;
    }
	
	/**
	 * Получить голоса пользователя за несколько комментариев одним запросом
	 * 
	 * @param int $userId
	 * @param array $commentIds
	 * @return array [comment_id => vote_value]
	 */
	public function getUserVotesForComments(int $userId, array $commentIds): array
	{
		if (empty($commentIds)) {
			return [];
		}

		$placeholders = [];
		$params = ['user_id' => $userId];
		
		foreach ($commentIds as $index => $id) {
			$key = 'cid_' . $index;
			$placeholders[] = ':' . $key;
			$params[$key] = (int)$id;
		}

		$sql = "SELECT votable_id, vote_type 
				FROM {$this->table}
				WHERE user_id = :user_id 
				  AND votable_id IN (" . implode(',', $placeholders) . ")
				  AND votable_type = 'comment'";

		$rows = $this->db->fetchAll($sql, $params);

		$result = [];
		foreach ($rows as $row) {
			$result[(int)$row['votable_id']] = (int)$row['vote_type'];
		}

		return $result;
	}
	
	/**
	 * Получить голоса пользователя за несколько историй одним запросом
	 * 
	 * @param int $userId
	 * @param array $storyIds
	 * @return array [story_id => vote_value]
	 */
	public function getUserVotesForStories(int $userId, array $storyIds): array
	{
		if (empty($storyIds)) {
			return [];
		}

		$placeholders = [];
		$params = ['user_id' => $userId];
		
		foreach ($storyIds as $index => $id) {
			$key = 'sid_' . $index;
			$placeholders[] = ':' . $key;
			$params[$key] = (int)$id;
		}

		$sql = "SELECT votable_id, vote_type 
				FROM {$this->table}
				WHERE user_id = :user_id 
				  AND votable_id IN (" . implode(',', $placeholders) . ")
				  AND votable_type = 'story'";

		$rows = $this->db->fetchAll($sql, $params);

		$result = [];
		foreach ($rows as $row) {
			$result[(int)$row['votable_id']] = (int)$row['vote_type'];
		}

		return $result;
	}
}