<?php

namespace App\Modules\Admin\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

class AuditLog extends Model
{
    protected string $table = 'audit_logs';
    protected bool $useSoftDeletes = false;

    /**
     * Поиск и фильтрация логов с постраничной навигацией
     */
    public function getFilteredLogs(
        int $limit,
        int $offset,
        ?int $userId,
        ?string $action,
        ?string $search,
        ?string $category = null
    ): array {
        $sql = "SELECT * FROM `audit_logs` WHERE 1=1";
        $bindings = [];

        if ($userId !== null) {
            $sql .= " AND `user_id` = :user_id";
            $bindings[':user_id'] = $userId;
        }

        if ($action !== null) {
            $sql .= " AND `action` = :action";
            $bindings[':action'] = $action;
        }

        if ($category !== null && $category !== '') {
            $sql .= " AND `category` = :category";
            $bindings[':category'] = $category;
        }

        if ($search !== null) {
            $sql .= " AND (`description` LIKE :search_desc OR `action` LIKE :search_action OR `username` LIKE :search_username)";
            $bindings[':search_desc'] = '%' . $search . '%';
            $bindings[':search_action'] = '%' . $search . '%';
            $bindings[':search_username'] = '%' . $search . '%';
        }

        $limit = (int)$limit;
        $offset = (int)$offset;
        $sql .= " ORDER BY `id` DESC LIMIT {$limit} OFFSET {$offset}";

        return $this->db->fetchAll($sql, $bindings);
    }

    /**
     * Подсчет общего количества строк с учетом текущих фильтров (для пагинации)
     */
    public function getFilteredCount(
        ?int $userId,
        ?string $action,
        ?string $search,
        ?string $category = null
    ): int {
        $sql = "SELECT COUNT(*) FROM `audit_logs` WHERE 1=1";
        $bindings = [];

        if ($userId !== null) {
            $sql .= " AND `user_id` = :user_id";
            $bindings['user_id'] = $userId;
        }

        if ($action !== null) {
            $sql .= " AND `action` = :action";
            $bindings['action'] = $action;
        }

        if ($category !== null && $category !== '') {
            $sql .= " AND `category` = :category";
            $bindings['category'] = $category;
        }

        if ($search !== null) {
            $sql .= " AND (`description` LIKE :search_desc OR `action` LIKE :search_action OR `username` LIKE :search_username)";
            $bindings['search_desc'] = '%' . $search . '%';
            $bindings['search_action'] = '%' . $search . '%';
            $bindings['search_username'] = '%' . $search . '%';
        }

        return (int)$this->db->fetchColumn($sql, $bindings);
    }

 
	
    /**
     * Получить список уникальных экшенов для выпадающего списка в UI
     * 
     * @return array Плоский массив строк: ['login', 'logout', 'create_story', ...]
     */
    public function getUniqueActions(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT `action` FROM `audit_logs` WHERE `action` IS NOT NULL ORDER BY `action` ASC"
        );
        
        // ✅ Извлекаем только значения поля 'action' в плоский массив
        return array_column($rows, 'action');
    }

    /**
     * Получить список уникальных категорий
     * 
     * @return array Плоский массив строк: ['general', 'moderation', 'admin', ...]
     */
    public function getUniqueCategories(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT `category` FROM `audit_logs` WHERE `category` IS NOT NULL ORDER BY `category` ASC"
        );
        
        return array_column($rows, 'category');
    }
	

    /**
     * Получить записи по категории (для модераторского раздела)
     */
    public function getByCategory(string $category, int $limit = 50, int $offset = 0): array
    {
        $limit = (int)$limit;
        $offset = (int)$offset;

        $sql = "SELECT * FROM `audit_logs` 
                WHERE `category` = :category 
                ORDER BY `id` DESC 
                LIMIT {$limit} OFFSET {$offset}";

        return $this->db->fetchAll($sql, [':category' => $category]);
    }

    /**
     * Подсчёт записей по категории
     */
    public function countByCategory(string $category): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `audit_logs` WHERE `category` = :category",
            [':category' => $category]
        );
    }
}
