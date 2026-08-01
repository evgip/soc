<?php

declare(strict_types=1);

namespace App\Modules\Users\Middleware;

use W3a\Core\Contracts\UserIdProviderInterface;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Http\Session;
use W3a\Core\Http\Middleware\MiddlewareInterface;

/**
 * Middleware для проверки факта авторизации пользователя.
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Session $session,
        private readonly UserIdProviderInterface $userIdProvider
    ) {
    }

    /**
     * Обработка запроса.
     * Возвращает Response (для прерывания) или результат следующего middleware.
     */
    public function handle(callable $next): mixed
    {
        $userId = $this->userIdProvider->getUserId();

        // Если пользователь не авторизован
        if ($userId === null || (int)$userId <= 0) {
            $this->session->flash('error', 'Необходима авторизация для доступа к этой странице.');
            
            // Сохраняем URL для возврата после успешной авторизации
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            
            // Возвращаем объект RedirectResponse вместо выбрасывания исключения
            // Это корректно прерывает цепочку middleware и отправляет редирект
            return new RedirectResponse('/login');
        }

        // Пользователь авторизован, передаем управление дальше
        return $next();
    }
}