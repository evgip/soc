<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Models\AuditLog;
use App\Modules\Admin\Models\Audit;
use W3a\Core\Database\Database;

/**
 * Сервис для работы с логами аудита.
 */
class AdminAuditService
{
    private AuditLog $auditLogModel;
    private Audit $auditModel;
    private Database $db;

    public function __construct(
        AuditLog $auditLogModel,
        Audit $auditModel,
        Database $db
    ) {
        $this->auditLogModel = $auditLogModel;
        $this->auditModel = $auditModel;
        $this->db = $db;
    }

    public function getFilteredLogs(
        int $perPage,
        int $offset,
        ?int $filterUserId = null,
        ?string $filterAction = null,
        ?string $searchQuery = null,
        ?string $filterCategory = null
    ): array {
        $logs = $this->auditLogModel->getFilteredLogs(
            $perPage,
            $offset,
            $filterUserId,
            $filterAction,
            $searchQuery,
            $filterCategory
        );

        foreach ($logs as &$log) {
            $payloadField = $log['payload'] ?? '';
            $log['decoded_payload'] = $payloadField ? json_decode($payloadField, true) : [];
        }

        return $logs;
    }

    public function getUniqueActions(): array
    {
        return $this->auditLogModel->getUniqueActions();
    }

    public function getUniqueCategories(): array
    {
        return $this->auditLogModel->getUniqueCategories();
    }

    public function getFilteredCount(
        ?int $filterUserId = null,
        ?string $filterAction = null,
        ?string $searchQuery = null,
        ?string $filterCategory = null
    ): int {
        return $this->auditLogModel->getFilteredCount(
            $filterUserId,
            $filterAction,
            $searchQuery,
            $filterCategory
        );
    }

    public function getRecentSecurityAlerts(): array
    {
        // ✅ Используем внедрённую модель
        return $this->auditModel->getRecentSecurityAlerts();
    }

    public function clearAuditLogs(): bool
    {
        try {
            // ✅ Используем внедрённый Database
            $this->db->execute("TRUNCATE TABLE `audit_logs`");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}