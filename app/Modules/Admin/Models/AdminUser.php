<?php

namespace App\Modules\Admin\Models;

use W3a\Core\Database\Model;

class AdminUser extends Model
{
    protected string $table = 'users';

    protected array $fillable = [
        'is_active',
        'type'
    ];

    /**
     * Выборка реестра учетных записей для панели модератора
     */
    public function getAdminUsersList(int $limit = 100): array
    {
        $sql = "SELECT id, name, email, role, is_active, created_at FROM `users` ORDER BY id DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Инвертирование флага активации аккаунта на уровне СУБД
     */
    public function toggleActivationStatus(int $userId): int
    {
        $user = $this->find($userId);
        if (!$user) {
            return -1;
        }

        $newStatus = ((int)$user['is_active'] === 1) ? 0 : 1;

        $this->update($userId, [
            'is_active' => $newStatus
        ]);

        return $newStatus;
    }
}
