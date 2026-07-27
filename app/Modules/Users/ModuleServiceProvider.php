<?php

declare(strict_types=1);

namespace App\Modules\Users;

use W3a\Core\Container;
use W3a\Core\Database;
use W3a\Core\Logger;
use W3a\Core\Session;
use W3a\Core\Audit;
use W3a\Core\Security\UserContext;
use W3a\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use W3a\Core\Events\EventDispatcher;
use W3a\Core\Events\Listeners\AuditListener;

use App\Modules\Users\Events\UserBanned;
use App\Modules\Users\Events\UserUnbanned;

use App\Modules\Users\Models\User;
use App\Modules\Users\Models\Notification;
use App\Modules\Users\Models\RateLimit;
use App\Modules\Users\Services\UserService;
use App\Modules\Users\Services\AvatarService;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        $container->singleton(User::class, function (Container $c) {
            return new User($c->get(Database::class), $c->get(Logger::class));
        });
        
        $container->singleton(Notification::class, function (Container $c) {
            return new Notification($c->get(Database::class), $c->get(Logger::class));
        });
        
        $container->singleton(RateLimit::class, function (Container $c) {
            return new RateLimit($c->get(Database::class), $c->get(Logger::class));
        });
        
        // === СЕРВИСЫ ===
        $container->singleton(UserService::class, function (Container $c) {
            return new UserService(
                $c->get(User::class),
                $c->get(Session::class)
            );
        });
        
        $container->singleton(AvatarService::class, function (Container $c) {
            return new AvatarService($c->get(Session::class));
        });

        // === СЛУШАТЕЛИ ===
        $container->singleton(AuditListener::class, function (Container $c) {
            return new AuditListener($c->get(Audit::class));
        });

        // ✅ 2. НОВОЕ: Factory для UserContext. 
        // Контейнер сам выполнит этот код при первом запросе UserContext и запомнит результат.
        $container->singleton(UserContext::class, function (Container $c) {
            // Читаем ID из сессии (используем $_SESSION, как в вашем AuthMiddleware)
            $userId = (int)($_SESSION['user_id'] ?? 0);
            
            if ($userId > 0) {
                $userModel = $c->get(User::class);
                $user = $userModel->find($userId);
                
                // ⚠️ ОСТАВЬТЕ ТОЛЬКО ОДИН ВАРИАНТ, который подходит вашей БД:
                
                // ВАРИАНТ А: Если есть поле `role`
                $role = $user['role'] ?? 'user';
                $isAdmin = ($role === 'admin');
                $isModerator = ($role === 'moderator' || $role === 'admin');
                
                /* 
                // ВАРИАНТ Б: Если есть поля `is_admin` и `is_moderator`
                $isAdmin = (bool)($user['is_admin'] ?? false);
                $isModerator = (bool)($user['is_moderator'] ?? false) || $isAdmin;
                */
            } else {
                // Гостевой пользователь
                $isAdmin = false;
                $isModerator = false;
            }

            return new UserContext(
                id: $userId,
                isAdmin: $isAdmin,
                isModerator: $isModerator
            );
        });
    }

    public function boot(): void
    {
        $dispatcher = $this->container->get(EventDispatcher::class);
        $auditListener = $this->container->get(AuditListener::class);

        $dispatcher->listen(UserBanned::class, [$auditListener, 'handle']);
        $dispatcher->listen(UserUnbanned::class, [$auditListener, 'handle']);
    }
}