<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Models;

use W3a\Core\Model;

/**
 * Модель ревизий wiki страниц.
 *
 * Отвечает за хранение истории изменений wiki страниц.
 */
class WikiRevision extends Model
{
    protected string $table = 'wiki_revisions';

    protected array $fillable = [
        'wiki_page_id',
        'revision_number',
        'content',
        'edit_summary',
        'user_id'
    ];

    /**
     * Получить все ревизии страницы
     */
    public function getForPage(int $pageId): array
    {
        $sql = "SELECT wr.*, u.username
                FROM {$this->table} wr
                LEFT JOIN users u ON wr.user_id = u.id
                WHERE wr.wiki_page_id = :page_id
                ORDER BY wr.revision_number DESC";

        return $this->db->fetchAll($sql, ['page_id' => $pageId]);
    }

    /**
     * Получить последнюю ревизию
     */
    public function getLatest(int $pageId): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE wiki_page_id = :page_id
                ORDER BY revision_number DESC
                LIMIT 1";

        return $this->db->fetchOne($sql, ['page_id' => $pageId]);
    }

    /**
     * Получить следующий номер ревизии
     */
    public function getNextRevisionNumber(int $pageId): int
    {
        $latest = $this->getLatest($pageId);
        return $latest ? $latest['revision_number'] + 1 : 1;
    }

    /**
     * Получить конкретную ревизию
     */
    public function getByNumber(int $pageId, int $revisionNumber): ?array
    {
        $sql = "SELECT wr.*, u.username
                FROM {$this->table} wr
                LEFT JOIN users u ON wr.user_id = u.id
                WHERE wr.wiki_page_id = :page_id 
                  AND wr.revision_number = :revision_number
                LIMIT 1";

        return $this->db->fetchOne($sql, [
            'page_id' => $pageId,
            'revision_number' => $revisionNumber
        ]);
    }

    /**
     * Получить количество ревизий для страницы
     */
    public function getCountForPage(int $pageId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM {$this->table} WHERE wiki_page_id = :page_id",
            ['page_id' => $pageId]
        );
    }

    /**
     * Удалить все ревизии страницы
     */
    public function deleteForPage(int $pageId): void
    {
        $this->db->execute(
            "DELETE FROM {$this->table} WHERE wiki_page_id = :page_id",
            ['page_id' => $pageId]
        );
    }
}