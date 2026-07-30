<?php

namespace App\Modules\Moderations\Models;

use W3a\Core\Database\Model;

class ModNote extends Model
{
    protected string $table = 'mod_notes';

    protected array $fillable = [
        'user_id',
        'moderator_id',
        'note',
        'is_private',
        'deleted_at'
    ];

    /**
     * Получить все заметки о конкретном пользователе
     */
    public function getNotesByUser(int $userId, int $limit = 50): array
    {
        $sql = "SELECT mn.*, u.username AS moderator_name 
                FROM `mod_notes` mn
                LEFT JOIN `users` u ON u.id = mn.moderator_id
                WHERE mn.`user_id` = :user_id AND mn.`deleted_at` IS NULL
                ORDER BY mn.`created_at` DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Получить последние заметки (для общей ленты)
     */
    public function getRecentNotes(int $limit = 50, bool $onlyPublic = false): array
    {
        $privacyCondition = $onlyPublic ? " AND mn.`is_private` = 0" : "";
        $sql = "SELECT mn.*, u.username AS moderator_name, target.username AS target_username
                FROM `mod_notes` mn
                LEFT JOIN `users` u ON u.id = mn.moderator_id
                LEFT JOIN `users` target ON target.id = mn.user_id
                WHERE mn.`deleted_at` IS NULL {$privacyCondition}
                ORDER BY mn.`created_at` DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Мягкое удаление заметки (soft delete)
     */
    public function deleteNote(int $id): bool
    {
        return $this->db->execute("
            UPDATE `{$this->table}` 
            SET `deleted_at` = CURRENT_TIMESTAMP 
            WHERE `id` = :id AND `deleted_at` IS NULL
        ", ['id' => $id]) > 0;
    }
}