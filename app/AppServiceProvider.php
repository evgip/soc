<?php

declare(strict_types=1);

namespace App;

use W3a\Core\Contracts\RateLimitStorageInterface;
use W3a\Core\Contracts\UserIdProviderInterface;
use W3a\Core\Contracts\AuditStorageInterface;
use W3a\Core\Contracts\BannedIpRepositoryInterface;
use W3a\Core\Contracts\ErrorHandlerInterface;

use W3a\Core\Database\Database;
use W3a\Core\Foundation\Container;
use W3a\Core\Foundation\Config;
use W3a\Core\Http\Router;

class AppServiceProvider
{
    public function register(Container $container): void
    {
        // =========================================================================
        // 1. СВЯЗЫВАНИЕ ИНТЕРФЕЙСОВ (ленивая загрузка через замыкания)
        // =========================================================================
        
        $container->singleton(RateLimitStorageInterface::class, fn($c) => 
            new \App\Modules\Users\Services\RateLimitStorage($c->get(Database::class), $c->get(Config::class))
        );

        $container->singleton(UserIdProviderInterface::class, fn($c) => 
            new \App\Modules\Auth\Services\UserIdProvider()
        );

        $container->singleton(AuditStorageInterface::class, fn($c) => 
            new \App\Modules\Admin\Services\AuditStorage($c->get(Database::class))
        );

        $container->singleton(BannedIpRepositoryInterface::class, fn($c) => 
            new \App\Modules\Admin\Services\BannedIpRepository($c->get(Database::class))
        );

        // Файлов этих нет
       // $container->singleton(UniqueCheckerInterface::class, fn($c) => 
         //   new \App\Modules\Users\Services\UniqueChecker($c->get(Database::class))
        //);

        $container->singleton(ErrorHandlerInterface::class, function (Container $c) {
            return new ErrorHandler($c);
        });

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
            \W3a\Core\Http\Middleware\CsrfMiddleware::class,
        ]);

        $router->addMiddlewareGroup('guest', [
            \W3a\Core\Http\Middleware\GuestMiddleware::class,
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