<?php
// app/Modules/Errors/Controllers/ErrorsController.php

namespace App\Modules\Errors\Controllers;

use App\BaseController;

class ErrorsController extends BaseController
{
	/**
	 * Ошибка 404 —  Страница не найдена
	 */
	public function notFound(string $message = "Страница не найдена"): void
	{
		http_response_code(404);

		$this->render('errors/404', [
			'title' => 'Ошибка 404 — cтраница не найдена',
			'message' => $message,
			'statusCode' => 404,
		]);
	}

	/**
	 * Ошибка 500 —  Ошибка сервера
	 */
	public function serverError(string $message = "Ошибка сервера"): void
	{
		http_response_code(500);

		$this->render('errors/500', [
			'title' => 'Ошибка 400 — cтраница не найдена',
			'message' => $message,
			'statusCode' => 500,
		]);
	}


	/**
	 * Ошибка 419 — CSRF-токен недействителен (срок действия формы истёк)
	 */
	public function csrf(string $message = "Срок действия формы истёк"): void
	{
		http_response_code(419);

		$this->render('errors/419', [
			'title' => 'Ошибка 419 — срок действия формы истёк',
			'message' => $message,
			'statusCode' => 419,
		]);
	}

	/**
	 * Ошибка 403 — Доступ запрещён
	 */
	public function forbidden(string $message = "Доступ запрещён"): void
	{
		http_response_code(403);

		$this->render('errors/403', [
			'title' => 'Ошибка 403 — доступ запрещён',
			'message' => $message,
			'statusCode' => 403,
		]);
	}


	/**
	 * Страница ошибки 429 - Too Many Requests
	 */
	public function tooManyRequests(string $message = "Превышен лимит запросов"): void
	{
		http_response_code(429);

		$retryAfter = config('rate_limit.retry_after', 60, 'int');
		header("Retry-After: {$retryAfter}");

		$this->render('errors/429', [
			'title' => '429 - Слишком много запросов',
			'message' => $message,
			'retryAfter' => $retryAfter
		]);
		exit;
	}

	/**
	 * HTTP 400 Bad Request
	 */
	public function badRequest(string $message = ''): void
	{
		http_response_code(400);
		$this->render('errors/400', [
			'title' => 'Некорректный запрос',
			'message' => $message ?: 'Запрос содержит некорректные параметры',
		]);
	}

	/**
	 * Универсальный метод для других кодов
	 */
	public function show(int $code, string $message = ''): void
	{
		http_response_code($code);
		$this->render("errors/{$code}", [
			'title' => "Ошибка {$code}",
			'message' => $message,
		]);
	}
}
