<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use W3a\Core\Support\Audit;
use W3a\Core\Support\Logger;
use App\Modules\Comments\Models\Comment;
use App\Modules\Mail\Core\Mailer;
use App\Modules\Stories\Services\RankingService; 

/**
 * Сервис для инструментов разработчика.
 */
class AdminToolsService
{
    private Audit $audit;
    private Logger $logger;
    private Comment $commentModel;
    private Mailer $mailer;
    private RankingService $rankingService;

    public function __construct(
        Audit $audit,
        Logger $logger,
        Comment $commentModel,
        Mailer $mailer,
        RankingService $rankingService
    ) {
        $this->audit = $audit;
        $this->logger = $logger;
        $this->commentModel = $commentModel;
        $this->mailer = $mailer;
        $this->rankingService = $rankingService;
    }

    public function compileAssets(): void
    {
        \W3a\Core\View\Asset::forceRebuild();
        $this->audit->log('admin.assets_compile', 'Администратор запустил ручную сборку CSS ассетов через панель инструментов', 'admin');
    }

    public function clearFileLogs(): int
    {
        $logDir = dirname(__DIR__, 4) . '/storage/logs/';
        $files = ['app.log', 'audit.log'];
        $clearedCount = 0;

        foreach ($files as $file) {
            $filePath = $logDir . $file;
            if (file_exists($filePath)) {
                file_put_contents($filePath, '');
                $clearedCount++;
            }
        }

        $this->audit->log('admin.tools_clear_files', 'Администратор очистил текстовые файлы системных логов на диске', 'admin');

        return $clearedCount;
    }

    public function cacheRoutes(object $router): void
    {
        $router->compileCache();
        $this->audit->log('admin.cache_routes_compiled', 'Администратор скомпилировал кэш маршрутов фреймворка', 'admin');
    }

    public function clearCacheRoutes(object $router): void
    {
        $router->clearCache();
        $this->audit->log('admin.cache_routes_cleared', 'Администратор полностью удалил кэш маршрутов', 'admin');
    }

    public function sendTestEmail(string $email): ?string
    {
        $subject = 'Тестовое письмо — проверка настроек почты';
        $message = 'Это тестовое письмо для проверки работоспособности настроек почты в системе.';

        try {
            $success = $this->mailer->send($email, $subject, $message);

            if ($success) {
                $this->audit->log('admin.test_email_sent', "Администратор отправил тестовое письмо на {$email}", 'admin');
                return null;
            }

            return 'Не удалось отправить тестовое письмо. Проверьте настройки PHP mail() или SMTP.';
        } catch (\Exception $e) {
            return 'Ошибка при отправке письма: ' . $e->getMessage();
        }
    }

    /**
     * Пакетный пересчёт confidence_score для всех комментариев.
     */
    public function recalculateConfidenceScoreBatch(int $offset, int $batchSize = 1000): array
    {
        try {
            $total = $this->commentModel->getCommentsCount();
            $comments = $this->commentModel->getCommentsBatch($offset, $batchSize);

            $processed = 0;

            foreach ($comments as $comment) {
                try {
                    $confidenceScore = $this->rankingService->wilsonScore(
                        (int)$comment['score'],
                        (int)$comment['flag_count']
                    );

                    $this->commentModel->updateConfidenceScore(
                        (int)$comment['id'],
                        $confidenceScore
                    );

                    $processed++;
                } catch (\Exception $e) {
                    $this->logger->error('Failed to update confidence score', [
                        'comment_id' => $comment['id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $hasMore = ($offset + $processed) < $total;

            return [
                'processed' => $processed,
                'total' => $total,
                'hasMore' => $hasMore,
                'nextOffset' => $offset + $processed,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Recalculate batch error', [
                'offset' => $offset,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}