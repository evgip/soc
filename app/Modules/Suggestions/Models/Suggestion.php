<?php

declare(strict_types=1);

namespace App\Modules\Suggestions\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

/**
 * Модель для работы с предложениями изменений контента.
 * 
 * Хранит предложения от пользователей и модераторов для статей и комментариев.
 * Использует soft delete через поле deleted_at.
 * 
 * @property int $id
 * @property string $target_type Тип контента ('Story' или 'Comment')
 * @property int $target_id ID контента
 * @property int $user_id ID пользователя, предложившего изменения
 * @property string $proposed_data JSON с предлагаемыми изменениями
 * @property string $proposed_data_hash MD5 хеш proposed_data (generated column)
 * @property string $created_at
 * @property string|null $deleted_at
 */
class Suggestion extends Model
{
    protected string $table = 'content_suggestions';

    protected array $fillable = [
        'target_type',
        'target_id',
        'user_id',
        'proposed_data',
        'created_at',
        'deleted_at'
    ];
    
    // =========================================================================
    // МЕТОДЫ ДЛЯ ПУБЛИЧНОГО ИСПОЛЬЗОВАНИЯ
    // =========================================================================

    /**
     * Получить все активные предложения для конкретного контента.
     * 
     * Возвращает предложения с данными пользователя (username).
     * Используется для отображения блока "Активные предложения" на странице статьи.
     * 
     * @param string $targetType Тип контента ('Story' или 'Comment')
     * @param int $targetId ID контента
     * @return array Массив предложений с полем suggester_name
     */
    public function getActiveSuggestions(string $targetType, int $targetId): array
    {
        $sql = "SELECT s.*, u.username AS suggester_name
                FROM `{$this->table}` s
                JOIN `users` u ON s.user_id = u.id
                WHERE s.target_type = :target_type
                AND s.target_id = :target_id
                AND s.deleted_at IS NULL
                ORDER BY s.created_at DESC";

        return $this->db->fetchAll($sql, [
            'target_type' => $targetType,
            'target_id' => $targetId
        ]);
    }

    /**
     * Подсчитать количество уникальных пользователей с точно такими же proposed_data.
     * 
     * Используется для проверки достижения кворума.
     * Сравнивает JSON предложенных изменений для поиска идентичных предложений.
     * 
     * @param string $targetType Тип контента
     * @param int $targetId ID контента
     * @param string $proposedDataJson Нормализованный JSON с предложениями
     * @return int Количество уникальных пользователей
     */
    public function countMatchingSuggestions(string $targetType, int $targetId, string $proposedDataJson): int
    {
        $sql = "SELECT COUNT(DISTINCT user_id) AS count
                FROM `{$this->table}`
                WHERE target_type = :target_type
                AND target_id = :target_id
                AND proposed_data = :proposed_data
                AND deleted_at IS NULL";

        $result = $this->db->fetchOne($sql, [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'proposed_data' => $proposedDataJson
        ]);

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Мягко удалить все предложения для конкретного контента.
     * 
     * Вызывается после применения изменений (кворум или модератор).
     * Устанавливает deleted_at = NOW() для всех активных предложений.
     * 
     * @param string $targetType Тип контента
     * @param int $targetId ID контента
     * @return bool Успешно ли выполнено
     */
    public function deleteAllForTarget(string $targetType, int $targetId): bool
    {
        $sql = "UPDATE `{$this->table}` 
                SET deleted_at = NOW()
                WHERE target_type = :target_type
                AND target_id = :target_id
                AND deleted_at IS NULL";

        return $this->db->execute($sql, [
            'target_type' => $targetType,
            'target_id' => $targetId
        ]) > 0;
    }

    /**
     * Подсчитать количество активных предложений от конкретного пользователя.
     * 
     * Используется для проверки лимита предложений (MAX_USER_SUGGESTIONS).
     * Считает только предложения для конкретного контента.
     * 
     * @param string $targetType Тип контента
     * @param int $targetId ID контента
     * @param int $userId ID пользователя
     * @return int Количество активных предложений
     */
    public function countUserSuggestions(string $targetType, int $targetId, int $userId): int
    {
        $sql = "SELECT COUNT(*) AS count
                FROM `{$this->table}`
                WHERE target_type = :target_type
                AND target_id = :target_id
                AND user_id = :user_id
                AND deleted_at IS NULL";

        $result = $this->db->fetchOne($sql, [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'user_id' => $userId
        ]);

        return (int) ($result['count'] ?? 0);
    }
    
    // =========================================================================
    // МЕТОДЫ ДЛЯ МОДЕРАТОРОВ
    // =========================================================================

    /**
     * Получить все активные предложения для страницы модерации.
     */
    public function getAllActive(int $limit = 30, int $offset = 0, string $filter = ''): array
    {
        $sql = "SELECT s.*, 
                       u.username AS suggester_name,
                       CASE 
                           WHEN s.target_type = 'Comment' THEN c.story_id
                           ELSE NULL
                       END AS story_id
                FROM `{$this->table}` s
                JOIN `users` u ON s.user_id = u.id
                LEFT JOIN `comments` c ON s.target_type = 'Comment' AND s.target_id = c.id
                WHERE s.deleted_at IS NULL";

        $params = [];

        if (!empty($filter)) {
            $sql .= " AND s.target_type = :filter";
            $params['filter'] = $filter;
        }

        $sql .= " ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        // Привязываем параметры
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Подсчитать количество всех активных предложений.
     * 
     * Используется для:
     * - Пагинации на странице модерации
     * - Фильтров (все/статьи/комментарии)
     * - Счетчика в меню модерации
     * 
     * @param string $filter Фильтр по типу контента ('Story', 'Comment' или '' для всех)
     * @return int Количество активных предложений
     */
    public function countAllActive(string $filter = ''): int
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}` WHERE deleted_at IS NULL";
        $params = [];

        if (!empty($filter)) {
            $sql .= " AND target_type = :filter";
            $params['filter'] = $filter;
        }

        return (int) $this->db->fetchColumn($sql, $params);
    }
    
    // =========================================================================
    // ДОПОЛНИТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Получить предложение по ID с данными пользователя.
     * 
     * Переопределяет базовый метод find(), чтобы сразу подгружать username.
     * 
     * @param int $id ID предложения
     * @return array|null Данные предложения или null
     */
    public function findWithUser(int $id): ?array
    {
        $sql = "SELECT s.*, u.username AS suggester_name
                FROM `{$this->table}` s
                JOIN `users` u ON s.user_id = u.id
                WHERE s.id = :id
                AND s.deleted_at IS NULL
                LIMIT 1";

        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Получить предложения пользователя (для профиля или статистики).
     */
    public function getUserSuggestions(int $userId, int $limit = 50): array
    {
        $sql = "SELECT s.*
                FROM `{$this->table}` s
                WHERE s.user_id = :user_id
                AND s.deleted_at IS NULL
                ORDER BY s.created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
	
    /**
     * Проверить, существует ли уже такое предложение от этого пользователя.
     * 
     * Используется для защиты от дубликатов (дополнительно к уникальному индексу).
     * 
     * @param string $targetType Тип контента
     * @param int $targetId ID контента
     * @param int $userId ID пользователя
     * @param string $proposedDataJson Нормализованный JSON
     * @return bool True если дубликат существует
     */
    public function hasDuplicate(string $targetType, int $targetId, int $userId, string $proposedDataJson): bool
    {
        $sql = "SELECT COUNT(*) AS count
                FROM `{$this->table}`
                WHERE target_type = :target_type
                AND target_id = :target_id
                AND user_id = :user_id
                AND proposed_data = :proposed_data
                AND deleted_at IS NULL";

        $result = $this->db->fetchOne($sql, [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'user_id' => $userId,
            'proposed_data' => $proposedDataJson
        ]);

        return (int) ($result['count'] ?? 0) > 0;
    }

    /**
     * Получить статистику предложений для модераторов.
     * 
     * Возвращает количество предложений по типам и статусам.
     * 
     * @return array Статистика
     */
    public function getStatistics(): array
    {
        $sql = "SELECT 
                    target_type,
                    COUNT(*) AS total,
                    SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS processed
                FROM `{$this->table}`
                GROUP BY target_type";

        $results = $this->db->fetchAll($sql);

        $stats = [
            'total' => 0,
            'active' => 0,
            'processed' => 0,
            'by_type' => []
        ];

        foreach ($results as $row) {
            $stats['by_type'][$row['target_type']] = [
                'total' => (int) $row['total'],
                'active' => (int) $row['active'],
                'processed' => (int) $row['processed']
            ];
            $stats['total'] += (int) $row['total'];
            $stats['active'] += (int) $row['active'];
            $stats['processed'] += (int) $row['processed'];
        }

        return $stats;
    }
}