<?php

declare(strict_types=1);

namespace App\Modules\Votes\Controllers;

use App\BaseController;
use W3a\Core\Exceptions\JsonResponseException;
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
     * 
     * @param string $type Тип сущности (story/comment)
     * @param string $id ID сущности
     * @param string $direction Направление голоса (up/down)
     * 
     * @throws JsonResponseException
     */
    public function handle(string $type, string $id, string $direction): void
    {
        // 1. Быстрая валидация
        $this->validateInput($type, $id, $direction);

        $userContext = $this->getUserContext();
        $userId = $userContext['id'];
        $targetId = (int)$id;
        $voteValue = ($direction === 'down') ? -1 : 1;

        // 2. Обработка голоса
        try {
            $result = $this->voteService()->handleVote($userId, $type, $targetId, $voteValue);
        } catch (\Throwable $e) {

            $this->logError($e, 'Votes.handle');

            throw new JsonResponseException([
                'status' => 'error',
                'message' => 'Внутренняя ошибка сервера.',
            ], 500);
        }

        if (!$result['success']) {
            throw new JsonResponseException([
                'status' => 'error',
                'message' => $result['message'],
            ], 403);
        }

        // 3. Возвращаем актуальные данные
        throw new JsonResponseException([
            'status' => 'success',
            'new_score'  => $this->voteService()->getNewScore($type, $targetId),
            'vote_state' => $this->voteService()->getUserVote($userId, $type, $targetId),
        ], 200);
    }

    /**
     * Валидация входных параметров
     * 
     * @throws JsonResponseException Если параметры невалидны
     */
    private function validateInput(string $type, string $id, string $direction): void
    {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new JsonResponseException([
                'status' => 'error',
                'message' => 'Недопустимый тип сущности.',
            ], 400);
        }

        if (!ctype_digit($id) || (int)$id <= 0) {
            throw new JsonResponseException([
                'status' => 'error',
                'message' => 'Недопустимый ID.',
            ], 400);
        }

        if (!in_array($direction, self::ALLOWED_DIRECTIONS, true)) {
            throw new JsonResponseException([
                'status' => 'error',
                'message' => 'Недопустимое направление.',
            ], 400);
        }
    }
}
