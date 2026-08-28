<?php

declare(strict_types=1);

namespace App\Modules\Votes\Controllers;

use App\BaseController;
use W3a\Core\Http\JsonResponse;
use App\Modules\Votes\Services\VoteService;

class VotesController extends BaseController
{
    public function clap(string $id): JsonResponse
    {
        if (!ctype_digit($id) || (int)$id <= 0) {
            return $this->json(['status' => 'error', 'message' => 'Недопустимый ID.'], 400);
        }

        $userContext = $this->getUserContext();
        $targetId = (int)$id;

        try {
            $result = $this->service(VoteService::class)->handleClap($userContext['id'], $targetId);
        } catch (\Throwable $e) {
            $this->logError($e, 'Votes.clap');
            return $this->json(['status' => 'error', 'message' => 'Внутренняя ошибка сервера.'], 500);
        }

        if (!$result['success']) {
            return $this->json(['status' => 'error', 'message' => $result['message']], 403);
        }

        return $this->json([
            'status'     => 'success',
            'new_score'  => $result['new_score'],
            'user_claps' => $result['user_claps'],
        ], 200);
    }

    public function likeComment(string $id): JsonResponse
    {
        if (!ctype_digit($id) || (int)$id <= 0) {
            return $this->json(['status' => 'error', 'message' => 'Недопустимый ID.'], 400);
        }

        $userContext = $this->getUserContext();
        $targetId = (int)$id;

        try {
            $result = $this->service(VoteService::class)->handleCommentLike($userContext['id'], $targetId);
        } catch (\Throwable $e) {
            $this->logError($e, 'Votes.likeComment');
            return $this->json(['status' => 'error', 'message' => 'Внутренняя ошибка сервера.'], 500);
        }

        if (!$result['success']) {
            return $this->json(['status' => 'error', 'message' => $result['message']], 403);
        }

        return $this->json([
            'status'    => 'success',
            'liked'     => $result['liked'],
            'new_score' => $result['new_score'],
        ], 200);
    }
}