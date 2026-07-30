<?php

declare(strict_types=1);

namespace App\Modules\Users\Middleware;

use W3a\Core\Foundation\Container;
use W3a\Core\Contracts\UserIdProviderInterface;
use W3a\Core\Http\Session;
use W3a\Core\Http\Middleware\MiddlewareInterface;
use App\Modules\Users\Models\User;

/**
 * Middleware для создания и регистрации UserContext в контейнере.
 * Инициализирует контекст пользователя для текущего запроса.
 */
class UserContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Container $container,
        private readonly UserIdProviderInterface $userIdProvider
    ) {
    }

    public function handle(callable $next): mixed
    {
        $userId = (int)$this->userIdProvider->getUserId();

        if ($userId > 0) {
            $userModel = $this->container->get(User::class);
            $user = $userModel->find($userId);

            // ВАРИАНТ А: Если есть поле `role`
            $role = $user['role'] ?? 'user';
            $isAdmin = ($role === 'admin');
            $isModerator = ($role === 'moderator' || $role === 'admin');

            /*
            // ВАРИАНТ Б: Если есть поля `is_admin` и `is_moderator`
            $isAdmin = (bool)($user['is_admin'] ?? false);
            $isModerator = (bool)($user['is_moderator'] ?? false) || $isAdmin;
            */

            $currentUserContext = new UserContext(
                id: $userId,
                isAdmin: $isAdmin,
                isModerator: $isModerator
            );

            $this->container->instance(UserContext::class, $currentUserContext);
        } else {
            $guestContext = new UserContext(
                id: 0,
                isAdmin: false,
                isModerator: false
            );
            $this->container->instance(UserContext::class, $guestContext);
        }

        return $next();
    }
}