<?php

declare(strict_types=1);

namespace App;

use W3a\Core\Container;
use W3a\Core\Router;
use W3a\Core\Contracts\RateLimitStorageInterface;
use W3a\Core\Contracts\UserIdProviderInterface;
use W3a\Core\Contracts\AuditStorageInterface;
use W3a\Core\Contracts\BannedIpRepositoryInterface;
use W3a\Core\Contracts\UniqueCheckerInterface;
use W3a\Core\Contracts\ErrorHandlerInterface;

class AppServiceProvider
{
    public function register(Container $container): void
    {
        // =========================================================================
        // 1. СВЯЗЫВАНИЕ ИНТЕРФЕЙСОВ (ленивая загрузка через замыкания)
        // =========================================================================
        
        $container->singleton(RateLimitStorageInterface::class, fn($c) => 
            new \App\Modules\Users\Services\RateLimitStorage($c->get(\W3a\Core\Database::class), $c->get(\W3a\Core\Config::class))
        );

        $container->singleton(UserIdProviderInterface::class, fn($c) => 
            new \App\Modules\Auth\Services\UserIdProvider()
        );

        $container->singleton(AuditStorageInterface::class, fn($c) => 
            new \App\Modules\Admin\Services\AuditStorage($c->get(\W3a\Core\Database::class))
        );

        $container->singleton(BannedIpRepositoryInterface::class, fn($c) => 
            new \App\Modules\Admin\Services\BannedIpRepository($c->get(\W3a\Core\Database::class))
        );

        $container->singleton(UniqueCheckerInterface::class, fn($c) => 
            new \App\Modules\Users\Services\UniqueChecker($c->get(\W3a\Core\Database::class))
        );

        $container->singleton(ErrorHandlerInterface::class, fn($c) => 
            new \App\Modules\Errors\Services\ErrorHandler($c)
        );

        // =========================================================================
        // 2. РЕГИСТРАЦИЯ ГРУПП MIDDLEWARE (отложена до первого запроса роутера)
        // =========================================================================
        $this->registerMiddlewareGroups($container);
    }

    /**
     * Регистрирует группы middleware в роутере.
     * Вызывается только после того, как все основные сервисы уже зарегистрированы.
     */
    private function registerMiddlewareGroups(Container $container): void
    {
        $router = $container->get(Router::class);

        $router->addMiddlewareGroup('web', [
            \W3a\Core\Middleware\CsrfMiddleware::class,
        ]);

        $router->addMiddlewareGroup('guest', [
            \W3a\Core\Middleware\GuestMiddleware::class,
        ]);

        $router->addMiddlewareGroup('auth', [
            \App\Modules\Users\Middleware\AuthMiddleware::class,
            \App\Modules\Users\Middleware\BanCheckMiddleware::class,
        ]);

        $router->addMiddlewareGroup('moderator', [
            \App\Modules\Users\Middleware\AuthMiddleware::class,
            \App\Modules\Users\Middleware\BanCheckMiddleware::class,
            \App\Modules\Users\Middleware\ModeratorMiddleware::class,
        ]);

        $router->addMiddlewareGroup('admin', [
            \App\Modules\Users\Middleware\AuthMiddleware::class,
            \App\Modules\Users\Middleware\BanCheckMiddleware::class,
            \App\Modules\Users\Middleware\AdminMiddleware::class,
        ]);
        
        $router->addMiddlewareGroup('context', [
            \App\Modules\Users\Middleware\UserContextMiddleware::class,
        ]);
    }
}