<?php

namespace App\Modules\Messages\Models;

use W3a\Core\Model;

class Message extends Model
{
    protected string $table = 'messages';

    protected array $fillable = [
        'sender_id',
        'conversation_id',
        'message',
        'is_read'
    ];

    /**
     * Fetch a paginated chunk of messages for a chat room, sorted chronologically
     */
    public function getPaginatedChatHistory(int $conversationId, int $limit = 15, int $offset = 0): array
    {
        $sql = "SELECT * FROM (
                    SELECT m.*, u.username as sender_name, up.avatar as sender_avatar 
                    FROM `messages` m
                    JOIN `users` u ON m.sender_id = u.id
                    LEFT JOIN `user_profiles` up ON u.id = up.user_id
                    WHERE m.conversation_id = :cid AND m.deleted_at IS NULL
                    ORDER BY m.id DESC 
                    LIMIT :limit OFFSET :offset
                ) sub 
                ORDER BY id ASC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':cid', $conversationId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Calculate the absolute total message volume inside a specific chat room
     */
    public function getTotalMessageCount(int $conversationId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `messages` WHERE `conversation_id` = :cid AND `deleted_at` IS NULL",
            ['cid' => $conversationId]
        );
    }

    /**
     * Fetch standard linear message payload stacks bound to an conversation room channel key
     */
    public function getChatHistory(int $conversationId): array
    {
        return $this->db->fetchAll("
            SELECT m.*, u.username as sender_name, up.avatar as sender_avatar 
            FROM `messages` m
            JOIN `users` u ON m.sender_id = u.id
            LEFT JOIN `user_profiles` up ON u.id = up.user_id
            WHERE m.conversation_id = :cid AND m.deleted_at IS NULL
            ORDER BY m.id ASC LIMIT 100
        ", ['cid' => $conversationId]);
    }

    /**
     * Instantly mark an conversation message thread bundle as read internally
     */
    public function markAsRead(int $conversationId, int $readerId): void
    {
        $this->db->execute(
            "UPDATE `messages` SET `is_read` = 1 WHERE `conversation_id` = :cid AND `sender_id` != :rid AND `is_read` = 0",
            ['cid' => $conversationId, 'rid' => $readerId]
        );
    }
}