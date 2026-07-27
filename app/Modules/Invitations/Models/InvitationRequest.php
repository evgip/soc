<?php

namespace App\Modules\Invitations\Models;

use W3a\Core\Model;

class InvitationRequest extends Model
{
    protected string $table = 'invitation_requests';

    protected array $fillable = [
        'email',
        'reason',
        'ip_address',
        'status'
    ];

    /**
     * Создать запрос на приглашение
     */
    public function createRequest(string $email, string $reason, string $ip): int
    {
        return $this->create([
            'email' => $email,
            'reason' => $reason,
            'ip_address' => $ip,
            'status' => 'pending'
        ]);
    }

    /**
     * Проверить, есть ли уже запрос с таким email
     */
    public function hasPendingRequest(string $email): bool
    {
        // ✅ Используем $this->db->fetchColumn()
        $count = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM invitation_requests WHERE email = :email AND status = 'pending'",
            ['email' => $email]
        );
        return $count > 0;
    }

    /**
     * Получить все запросы (для админки)
     */
    public function getAllRequests(string $status = 'pending'): array
    {
        // ✅ Используем $this->db->fetchAll()
        return $this->db->fetchAll(
            "SELECT * FROM invitation_requests WHERE status = :status ORDER BY created_at DESC",
            ['status' => $status]
        );
    }

    /**
     * Одобрить запрос
     */
    public function approveRequest(int $requestId): bool
    {
        return $this->update($requestId, ['status' => 'approved']);
    }

    /**
     * Отклонить запрос
     */
    public function rejectRequest(int $requestId): bool
    {
        return $this->update($requestId, ['status' => 'rejected']);
    }
}