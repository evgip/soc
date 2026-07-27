<?php

namespace App\Modules\Users\Models;

use W3a\Core\Model;
use W3a\Core\Database;
use W3a\Core\Logger;

class Notification extends Model
{
    protected string $table = 'user_notifications';

    protected array $fillable = [
        'user_id',
        'is_read'
    ];

    /**
     * Atomically flags all unread notification rows for a specific user as read
     */
    public function markAllAsRead(int $userId): void
    {
        $this->db->execute(
            "UPDATE `user_notifications` SET `is_read` = 1 WHERE `user_id` = :uid AND `is_read` = 0",
            ['uid' => $userId]
        );
    }
}