<?php

declare(strict_types=1);

namespace App\Modules\Errors\Services;

use W3a\Core\Contracts\ErrorHandlerInterface;
use W3a\Core\Foundation\Container;

class ErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private readonly Container $container
    ) {}

    public function render(int $code, string $message, array $context = []): void
    {
        http_response_code($code); // Гарантируем код ответа
        
        $controllerClass = \App\Modules\Errors\Controllers\ErrorsController::class;
        
        if (!class_exists($controllerClass)) {
            echo "<h1>Error $code</h1><p>" . htmlspecialchars($message) . "</p>";
            return;
        }

        try {
            $controller = $this->container->make($controllerClass);
            
            $response = match ($code) {
                400 => $controller->badRequest($message),
                403 => $controller->forbidden($message),
                404 => $controller->notFound($message),
                419 => $controller->csrf($message),
                429 => $controller->tooManyRequests($message),
                default => $controller->show($code, $message),
            };

            // Явно отправляем ответ, так как мы находимся вне стандартного цикла роутера
            if (method_exists($response, 'send')) {
                $response->send();
            } else {
                echo $response;
            }
        } catch (\Throwable $controllerError) {
            // Аварийный fallback, если даже модуль ошибок упал (например, не найден шаблон)
            echo "<h1>Ошибка {$code}</h1><p>" . htmlspecialchars($message) . "</p>";
            // Опционально: можно залогировать $controllerError
        }
    }
}