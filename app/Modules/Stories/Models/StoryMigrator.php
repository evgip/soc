<?php

declare(strict_types=1);

namespace App\Modules\Stories\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;
use W3a\Core\Support\HtmlSanitizer;

class StoryMigrator extends Model
{
    protected string $table = 'stories';

    // Для миграции нам нужны только эти поля
    protected array $fillable = [
        'id',
        'description',
        'description_json',
    ];

    private ?HtmlSanitizer $sanitizer;

    public function __construct(Database $db, Logger $logger, ?HtmlSanitizer $sanitizer = null)
    {
        parent::__construct($db, $logger);
        $this->sanitizer = $sanitizer;
    }

    /**
     * Мигрировать или протестировать конвертацию старых записей.
     *
     * @param bool $dryRun Если true, данные НЕ сохраняются в БД, а возвращаются для просмотра
     * @param int  $limit  Ограничить количество записей для теста (по умолчанию 5)
     * @return array Массив с результатами (или статистикой, если не dryRun)
     */
    public function processOldStories(bool $dryRun = true, int $limit = 5): array
    {
        $stories = $this->db->fetchAll(
            "SELECT `id`, `title`, `description` FROM `{$this->table}` 
             WHERE `description` IS NOT NULL AND `description` != '' 
             AND (`description_json` IS NULL OR `description_json` = '')
             LIMIT :limit",
            ['limit' => $limit]
        );

        $results = [];
        $count = 0;

        foreach ($stories as $story) {
            $blocks = $this->markdownToBasicBlocks($story['description']);
            
            // JSON_PRETTY_PRINT сделает вывод красивым и читаемым для глаза
            $json = json_encode([
                'time'    => time() * 1000,
                'blocks'  => $blocks,
                'version' => '2.30.7',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            if ($dryRun) {
                // В тестовом режиме возвращаем данные для просмотра
                $results[] = [
                    'id' => $story['id'],
                    'title' => $story['title'],
                    'original_markdown' => $story['description'],
                    'generated_json' => $json,
                    'blocks_count' => count($blocks),
                ];
            } else {
                // В боевом режиме сохраняем в БД (без pretty print для экономии места)
                $compactJson = json_encode([
                    'time'    => time() * 1000,
                    'blocks'  => $blocks,
                    'version' => '2.30.7',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $this->db->execute(
                    "UPDATE `{$this->table}` SET `description_json` = :json WHERE `id` = :id",
                    ['json' => $compactJson, 'id' => $story['id']]
                );
                $count++;
            }
        }

        return $dryRun ? $results : ['migrated_count' => $count];
    }

    /**
     * Простая эвристика для конвертации старого Markdown в блоки Editor.js.
     */
    private function markdownToBasicBlocks(string $markdown): array
    {
        $rawParagraphs = preg_split('/\n\s*\n/', trim($markdown));
        $blocks = [];

        foreach ($rawParagraphs as $para) {
            $para = trim($para);
            if ($para === '') continue;

            // Заголовок (#, ##, ###)
            if (preg_match('/^(#{1,3})\s+(.+)$/m', $para, $matches)) {
                $blocks[] = [
                    'type' => 'header',
                    'data' => [
                        'text' => htmlspecialchars(trim($matches[2]), ENT_QUOTES, 'UTF-8'),
                        'level' => min(strlen($matches[1]), 3),
                    ],
                ];
            } 
            // Цитата (>)
            elseif (preg_match('/^>\s+(.+)$/m', $para, $matches)) {
                $blocks[] = [
                    'type' => 'quote',
                    'data' => [
                        'text' => htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8'),
                        'caption' => '',
                        'alignment' => 'left',
                    ],
                ];
            } 
            // Обычный абзац
            else {
                $blocks[] = [
                    'type' => 'paragraph',
                    'data' => [
                        'text' => nl2br(htmlspecialchars($para, ENT_QUOTES, 'UTF-8'), false),
                    ],
                ];
            }
        }

        return empty($blocks) ? [['type' => 'paragraph', 'data' => ['text' => '']]] : $blocks;
    }
}