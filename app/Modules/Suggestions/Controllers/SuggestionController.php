<?php

declare(strict_types=1);

namespace App\Modules\Suggestions\Controllers;

use App\BaseController;
use W3a\Core\Http\Session;
use W3a\Core\Exceptions\JsonResponseException;
use App\Modules\Suggestions\Services\SuggestionService;
use App\Modules\Suggestions\Models\Suggestion;

/**
 * Контроллер предложений по изменениям (suggestions).
 * 
 * Обрабатывает:
 * - Получение активных предложений для сущности
 * - Просмотр истории изменений (change log)
 * - Создание новых предложений
 * - Поддержку существующих предложений
 * - Одобрение/отклонение предложений (модераторами)
 */
class SuggestionController extends BaseController
{
    /**
     * Получить Session из контейнера
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }

    /**
     * Получить активные предложения для сущности (AJAX endpoint)
     */
    public function index(string $targetType, string $targetId): void
    {
        $suggestions = $this->service(SuggestionService::class)->getActiveSuggestions(
            $targetType,
            (int) $targetId
        );

        $this->json([
            'suggestions' => $suggestions,
            'count' => count($suggestions)
        ]);
    }

    /**
     * Получить историю изменений (ленту предложений)
     * 
     * ВАЖНО: JsonResponseException пробрасывается дальше,
     * чтобы Application корректно обработал ответ.
     */
    public function log(string $targetType, string $targetId): void
    {
        try {
            $targetType = trim($targetType);
            $targetIdInt = (int) $targetId;

            if ($targetType === '' || $targetIdInt <= 0) {
                $this->json(['error' => 'Invalid parameters: target_type and target_id are required'], 400);
                return;
            }

            $limit = (int) $this->request->input('limit', 50);
            $limit = max(1, min($limit, 200));

            $logs = $this->service(SuggestionService::class)->getChangeLog(
                $targetType,
                $targetIdInt,
                $limit
            );

            $this->json([
                'success' => true,
                'logs' => $logs,
                'count' => count($logs),
                'limit' => $limit
            ]);
        } catch (JsonResponseException $e) {
            // ✅ НЕ перехватываем JsonResponseException — Application обработает
            throw $e;
        } catch (\Throwable $e) {
            // ✅ Логируем реальную ошибку через единый метод
            $this->logError($e, 'Suggestions.log');
            $this->json(['error' => 'Failed to retrieve change log'], 500);
        }
    }

    /**
     * Создать новое предложение
     */
    public function store(): void
    {
        try {
            $userContext = $this->getUserContext();

            if (!$userContext['isLoggedIn']) {
                $this->json(['error' => 'Authentication required'], 401);
                return;
            }

            $targetType = trim((string) $this->request->input('target_type', ''));
            $targetId = (int) $this->request->input('target_id', 0);
            $proposedDataRaw = $this->request->input('proposed_data');

            if ($targetType === '' || $targetId <= 0) {
                $this->json([
                    'error' => 'Missing or invalid required parameters: target_type, target_id'
                ], 400);
                return;
            }

            $proposedData = $this->parseProposedData($proposedDataRaw);
            if (empty($proposedData)) {
                $this->json(['error' => 'Invalid or empty proposed_data'], 400);
                return;
            }

            $suggestionId = $this->service(SuggestionService::class)->addSuggestion(
                $targetType,
                $targetId,
                $userContext['id'],
                $proposedData
            );

            $this->json([
                'success' => true,
                'suggestion_id' => $suggestionId,
                'message' => 'Suggestion added successfully'
            ], 201);
        } catch (JsonResponseException $e) {
            // ✅ НЕ перехватываем JsonResponseException
            throw $e;
        } catch (\Throwable $e) {
            // ✅ Логируем реальную ошибку через единый метод
            $this->logError($e, 'Suggestions.store');
            $this->json(['error' => 'Failed to create suggestion'], 500);
        }
    }

    /**
     * Универсальный парсер proposed_data
     */
    private function parseProposedData(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (is_string($data) && $data !== '') {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Поддержать существующее предложение (создать аналогичное)
     */
    public function support(string $id): void
    {
        try {
            $suggestionModel = $this->container->get(Suggestion::class);
            $suggestion = $suggestionModel->find((int) $id);

            if (!$suggestion) {
                $this->json(['error' => 'Suggestion not found'], 404);
                return;
            }

            $userContext = $this->getUserContext();

            $this->service(SuggestionService::class)->addSuggestion(
                $suggestion['target_type'],
                $suggestion['target_id'],
                $userContext['id'],
                json_decode($suggestion['proposed_data'], true)
            );

            $this->json(['success' => true]);
        } catch (JsonResponseException $e) {
            // ✅ НЕ перехватываем JsonResponseException
            throw $e;
        } catch (\Throwable $e) {
            // ✅ Логируем реальную ошибку через единый метод
            $this->logError($e, 'Suggestions.support');
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Одобрить предложение (для модераторов/админов)
     */
    public function approve(string $id): void
    {
        try {
            $userContext = $this->getUserContext();

            $this->service(SuggestionService::class)->approveSuggestion(
                (int) $id,
                $userContext['id']
            );

            $this->backWithMessage('Предложение одобрено и применено.', 'success');
        } catch (\Throwable $e) {
            // ✅ Логируем реальную ошибку
            $this->logError($e, 'Suggestions.approve');
            $this->backWithMessage($e->getMessage(), 'error');
        }
    }

    /**
     * Отклонить предложение (для модераторов/админов)
     */
    public function reject(string $id): void
    {
        try {
            $reason = $this->request->post('reason', '');
            $userContext = $this->getUserContext();

            $this->service(SuggestionService::class)->rejectSuggestion(
                (int) $id,
                $userContext['id'],
                $reason
            );

            $this->backWithMessage('Предложение отклонено.', 'success');
        } catch (\Throwable $e) {
            // ✅ Логируем реальную ошибку
            $this->logError($e, 'Suggestions.reject');
            $this->backWithMessage($e->getMessage(), 'error');
        }
    }
}