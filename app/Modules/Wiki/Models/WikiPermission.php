<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Models;

use W3a\Core\Model;

/**
 * Модель прав доступа к wiki.
 *
 * Отвечает за управление правами пользователей на wiki для тегов.
 */
class WikiPermission extends Model
{
    protected string $table = 'wiki_permissions';

    protected array $fillable = [
        'tag_id',
        'user_id',
        'can_edit',
        'can_delete',
        'granted_by'
    ];

    /**
     * Получить права пользователя для тега
     */
    public function getUserPermission(int $tagId, int $userId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table}
             WHERE tag_id = :tag_id AND user_id = :user_id
             LIMIT 1",
            [
                'tag_id' => $tagId,
                'user_id' => $userId
            ]
        );
    }

    /**
     * Получить всех редакторов тега
     */
    public function getTagEditors(int $tagId): array
    {
        $sql = "SELECT wp.*, u.username
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.user_id = u.id
                WHERE wp.tag_id = :tag_id
                ORDER BY u.username ASC";

        return $this->db->fetchAll($sql, ['tag_id' => $tagId]);
    }

    /**
     * Проверить наличие прав
     */
    public function hasPermission(int $tagId, int $userId): bool
    {
        $permission = $this->getUserPermission($tagId, $userId);
        return $permission !== null;
    }

    /**
     * Удалить все права для тега
     */
    public function deleteForTag(int $tagId): void
    {
        $this->db->execute(
            "DELETE FROM {$this->table} WHERE tag_id = :tag_id",
            ['tag_id' => $tagId]
        );
    }

    /**
     * Удалить все права пользователя
     */
    public function deleteForUser(int $userId): void
    {
        $this->db->execute(
            "DELETE FROM {$this->table} WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
    }

    /**
     * Получить количество редакторов для тега
     */
    public function getEditorsCount(int $tagId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM {$this->table} WHERE tag_id = :tag_id",
            ['tag_id' => $tagId]
        );
    }
}