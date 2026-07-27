<?php

declare(strict_types=1);

namespace App\Modules\Users\Middleware;

use W3a\Core\Contracts\UserIdProviderInterface;
use W3a\Core\Exceptions\RedirectException;
use W3a\Core\Session;
use W3a\Core\Middleware\MiddlewareInterface;

/**
 * Middleware для проверки факта авторизации пользователя.
 * 
 * Если пользователь не авторизован, перенаправляет его на страницу входа,
 * сохраняя текущий URL для возврата после успешной авторизации.
 * 
 * Примечание: Проверка на бан вынесена в отдельный BanCheckMiddleware,
 * который должен идти следом в цепочке middleware.
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Конструктор с инъекцией зависимостей.
     * Мы используем UserIdProviderInterface, чтобы не зависеть от статических вызовов.
     */
    public function __construct(
        private readonly Session $session,
        private readonly UserIdProviderInterface $userIdProvider
    ) {
    }

    /**
     * Обработка запроса.
     *
     * @param callable $next Следующий элемент в цепочке middleware
     * @return mixed
     */
    public function handle(callable $next): mixed
    {
        $userId = $this->userIdProvider->getUserId();

        // Если пользователь не авторизован (ID отсутствует или равен 0)
        if ($userId === null || (int)$userId <= 0) {
            $this->session->flash('error', 'Необходима авторизация для доступа к этой странице.');
            
            // Сохраняем URL, куда пользователь хотел попасть, 
            // чтобы контроллер логина мог вернуть его туда после успеха
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            
            // Выбрасываем исключение, которое перехватит Application
            throw new RedirectException('/login');
        }

        // Пользователь авторизован, передаем управление следующему middleware 
        // (например, BanCheckMiddleware) или контроллеру
        return $next();
    }
}