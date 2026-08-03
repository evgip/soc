<?php

declare(strict_types=1);

namespace App\Modules\Errors\Controllers;

use App\BaseController;
use W3a\Core\Http\ViewResponse;

class ErrorsController extends BaseController
{
    public function notFound(string $message = "Страница не найдена"): ViewResponse // <-- Убрали void
    {
        http_response_code(404);
        return $this->render('errors/404', [
            'title' => 'Ошибка 404 — страница не найдена',
            'message' => $message,
            'statusCode' => 404,
        ]);
    }

    public function serverError(string $message = "Ошибка сервера"): ViewResponse
    {
        http_response_code(500);
        return $this->render('errors/500', [
            'title' => 'Ошибка 500 — внутренняя ошибка сервера', // <-- Исправили опечатку в title
            'message' => $message,
            'statusCode' => 500,
        ]);
    }

    public function csrf(string $message = "Срок действия формы истёк"): ViewResponse
    {
        http_response_code(419);
        return $this->render('errors/419', [
            'title' => 'Ошибка 419 — срок действия формы истёк',
            'message' => $message,
            'statusCode' => 419,
        ]);
    }

    public function forbidden(string $message = "Доступ запрещён"): ViewResponse
    {
        http_response_code(403);
        return $this->render('errors/403', [
            'title' => 'Ошибка 403 — доступ запрещён',
            'message' => $message,
            'statusCode' => 403,
        ]);
    }

    public function tooManyRequests(string $message = "Превышен лимит запросов"): ViewResponse
    {
        http_response_code(429);
        $retryAfter = config('rate_limit.retry_after', 60, 'int');
        header("Retry-After: {$retryAfter}");

        return $this->render('errors/429', [
            'title' => '429 - Слишком много запросов',
            'message' => $message,
            'retryAfter' => $retryAfter
        ]);
    }

    public function badRequest(string $message = ''): ViewResponse
    {
        http_response_code(400);
        return $this->render('errors/400', [
            'title' => 'Некорректный запрос',
            'message' => $message ?: 'Запрос содержит некорректные параметры',
        ]);
    }

    public function show(int $code, string $message = ''): ViewResponse
    {
        http_response_code($code);
        return $this->render("errors/{$code}", [
            'title' => "Ошибка {$code}",
            'message' => $message,
        ]);
    }
}