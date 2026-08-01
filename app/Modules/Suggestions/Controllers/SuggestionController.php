<?php

declare(strict_types=1);

namespace App\Modules\Suggestions\Controllers;

use App\BaseController;
use W3a\Core\Http\JsonResponse;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Support\MessageBag;

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
     * Получить активные предложения для сущности (AJAX endpoint)
     */
    public function index(string $targetType, string $targetId): JsonResponse
    {
        $suggestions = $this->service(SuggestionService::class)->getActiveSuggestions(
            $targetType,
            (int) $targetId
        );

        return $this->json([
            'suggestions' => $suggestions,
            'count' => count($suggestions)
        ]);
    }

    /**
     * Получить историю изменений (ленту предложений)
     */
    public function log(string $targetType, string $targetId): JsonResponse
    {
        try {
            $targetType = trim($targetType);
            $targetIdInt = (int) $targetId;

            if ($targetType === '' || $targetIdInt <= 0) {
                return $this->json(['error' => 'Invalid parameters: target_type and target_id are required'], 400);
            }

            $limit = (int) $this->request->input('limit', 50);
            $limit = max(1, min($limit, 200));

            $logs = $this->service(SuggestionService::class)->getChangeLog(
                $targetType,
                $targetIdInt,
                $limit
            );

            return $this->json([
                'success' => true,
                'logs' => $logs,
                'count' => count($logs),
                'limit' => $limit
            ]);
        } catch (\Throwable $e) {
            // ✅ Логируем реальную ошибку через единый метод
            $this->logError($e, 'Suggestions.log');
            return $this->json(['error' => 'Failed to retrieve change log'], 500);
        }
    }

    /**
     * Создать новое предложение
     */
    public function store(): JsonResponse
    {
        try {
            $userContext = $this->getUserContext();

            if (!$userContext['isLoggedIn']) {
                return $this->json(['error' => 'Authentication required'], 401);
            }

            $targetType = trim((string) $this->request->input('target_type', ''));
            $targetId = (int) $this->request->input('target_id', 0);
            $proposedDataRaw = $this->request->input('proposed_data');

            if ($targetType === '' || $targetId <= 0) {
                return $this->json([
                    'error' => 'Missing or invalid required parameters: target_type, target_id'
                ], 400);
            }

            $proposedData = $this->parseProposedData($proposedDataRaw);
            if (empty($proposedData)) {
                return $this->json(['error' => 'Invalid or empty proposed_data'], 400);
            }

            $suggestionId = $this->service(SuggestionService::class)->addSuggestion(
                $targetType,
                $targetId,
                $userContext['id'],
                $proposedData
            );

            return $this->json([
                'success' => true,
                'suggestion_id' => $suggestionId,
                'message' => 'Suggestion added successfully'
            ], 201);
        } catch (\Throwable $e) {
            // ✅ Логируем реальную ошибку через единый метод
            $this->logError($e, 'Suggestions.store');
            return $this->json(['error' => 'Failed to create suggestion'], 500);
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
    public function support(string $id): JsonResponse
    {
        try {
            $suggestionModel = $this->container->get(Suggestion::class);
            $suggestion = $suggestionModel->find((int) $id);

            if (!$suggestion) {
                return $this->json(['error' => 'Suggestion not found'], 404);
            }

            $userContext = $this->getUserContext();

            $this->service(SuggestionService::class)->addSuggestion(
                $suggestion['target_type'],
                $suggestion['target_id'],
                $userContext['id'],
                json_decode($suggestion['proposed_data'], true)
            );

            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            // ✅ Логируем реальную ошибку через единый метод
            $this->logError($e, 'Suggestions.support');
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Одобрить предложение (для модераторов/админов)
     */
    public function approve(string $id): RedirectResponse
    {
        try {
            $userContext = $this->getUserContext();

            $this->service(SuggestionService::class)->approveSuggestion(
                (int) $id,
                $userContext['id']
            );

            MessageBag::flashMessage('success', 'Предложение одобрено и применено.');
            return $this->redirectBack();
        } catch (\Throwable $e) {
            $this->logError($e, 'Suggestions.approve');
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack();
        }
    }

    /**
     * Отклонить предложение (для модераторов/админов)
     */
    public function reject(string $id): RedirectResponse
    {
        try {
            $reason = $this->request->post('reason', '');
            $userContext = $this->getUserContext();

            $this->service(SuggestionService::class)->rejectSuggestion(
                (int) $id,
                $userContext['id'],
                $reason
            );

            MessageBag::flashMessage('success', 'Предложение отклонено.');
            return $this->redirectBack();
        } catch (\Throwable $e) {
            $this->logError($e, 'Suggestions.reject');
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack();
        }
    }
}