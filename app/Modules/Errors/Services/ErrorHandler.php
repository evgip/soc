<?php

declare(strict_types=1);

namespace App\Modules\Errors\Services;

use W3a\Core\Contracts\ErrorHandlerInterface;
use W3a\Core\Container;

/**
 * Реализация обработчика ошибок для основного приложения.
 * Связывает интерфейс ядра с существующим ErrorsController.
 */
class ErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private readonly Container $container
    ) {
    }

    public function render(int $code, string $message, array $context = []): void
    {
        $controllerClass = "App\\Modules\\Errors\\Controllers\\ErrorsController";
        
        if (!class_exists($controllerClass)) {
            echo "<h1>Error $code</h1><p>" . htmlspecialchars($message) . "</p>";
            return;
        }

        try {
            $controller = $this->container->make($controllerClass);
            
            // Маппинг кодов на методы контроллера (адаптируйте под ваши реальные методы)
            $method = match ($code) {
                400 => 'badRequest',
                403 => 'forbidden',
                404 => 'notFound',
                419 => 'csrf',
                default => 'show',
            };

            if ($method === 'show') {
                $controller->show($code, $message);
            } elseif (method_exists($controller, $method)) {
                $controller->$method($message);
            } else {
                $controller->show($code, $message); // Fallback внутри контроллера
            }
        } catch (\Throwable $controllerError) {
            // Если контроллер упал, показываем минималистичную ошибку
            echo "<h1>Critical Error</h1><p>Failed to render error page.</p>";
        }
    }
}