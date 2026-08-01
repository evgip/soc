<?php

declare(strict_types=1);

namespace App\Modules\Votes\Controllers;

use App\BaseController;
use W3a\Core\Http\JsonResponse;
use App\Modules\Votes\Services\VoteService;

/**
 * Контроллер голосования.
 * Маршрут защищён middleware: web + auth.
 */
class VotesController extends BaseController
{
    private const ALLOWED_TYPES = ['story', 'comment'];
    private const ALLOWED_DIRECTIONS = ['up', 'down'];

    /**
     * Получить VoteService из контейнера
     */
    private function voteService(): VoteService
    {
        return $this->service(VoteService::class);
    }

    /**
     * Обработка голоса за историю или комментарий
     */
    public function handle(string $type, string $id, string $direction): JsonResponse
    {
        // 1. Быстрая валидация (возвращает JsonResponse при ошибке или null при успехе)
        $validationResponse = $this->validateInput($type, $id, $direction);
        if ($validationResponse !== null) {
            return $validationResponse;
        }

        $userContext = $this->getUserContext();
        $userId = $userContext['id'];
        $targetId = (int)$id;
        $voteValue = ($direction === 'down') ? -1 : 1;

        // 2. Обработка голоса
        try {
            $result = $this->voteService()->handleVote($userId, $type, $targetId, $voteValue);
        } catch (\Throwable $e) {
            $this->logError($e, 'Votes.handle');
            
            return $this->json([
                'status' => 'error',
                'message' => 'Внутренняя ошибка сервера.',
            ], 500);
        }

        if (!$result['success']) {
            return $this->json([
                'status' => 'error',
                'message' => $result['message'],
            ], 403);
        }

        // 3. Возвращаем актуальные данные
        return $this->json([
            'status' => 'success',
            'new_score'  => $this->voteService()->getNewScore($type, $targetId),
            'vote_state' => $this->voteService()->getUserVote($userId, $type, $targetId),
        ], 200);
    }

    /**
     * Валидация входных параметров.
     * Возвращает JsonResponse с ошибкой, если параметры невалидны, иначе null.
     */
    private function validateInput(string $type, string $id, string $direction): ?JsonResponse
    {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return $this->json([
                'status' => 'error',
                'message' => 'Недопустимый тип сущности.',
            ], 400);
        }

        if (!ctype_digit($id) || (int)$id <= 0) {
            return $this->json([
                'status' => 'error',
                'message' => 'Недопустимый ID.',
            ], 400);
        }

        if (!in_array($direction, self::ALLOWED_DIRECTIONS, true)) {
            return $this->json([
                'status' => 'error',
                'message' => 'Недопустимое направление.',
            ], 400);
        }

        return null;
    }
}