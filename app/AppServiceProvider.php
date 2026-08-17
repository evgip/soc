<?php

declare(strict_types=1);

namespace App;

use W3a\Core\Contracts\RateLimitStorageInterface;
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
        // 1. СВЯЗЫВАНИЕ КОНТРАКТОВ ЯДРА
        // =========================================================================

        // Rate limiting: своя реализация с GC (rate_limit.gc_probability).
        // Альтернатива без GC (ядро):
        //   new \W3a\Core\Security\DatabaseRateLimitStorage($c->get(Database::class))
        $container->singleton(RateLimitStorageInterface::class, fn($c) =>
            new \App\Modules\Users\Services\RateLimitStorage($c->get(Database::class), $c->get(Config::class))
        );

        // Аудит: ядерная реализация (таблица audit_logs)
        $container->singleton(AuditStorageInterface::class, fn($c) =>
            new \W3a\Core\Audit\DatabaseAuditStorage($c->get(Database::class))
        );

        // Firewall: ядерная реализация (таблица banned_ips)
        $container->singleton(BannedIpRepositoryInterface::class, fn($c) =>
            new \W3a\Core\Security\DatabaseBannedIpRepository($c->get(Database::class))
        );

        // Ошибки: прикладной ErrorHandler (свой layout + views)
        $container->singleton(ErrorHandlerInterface::class, fn($c) =>
            new \App\Modules\Errors\Services\ErrorHandler($c)
        );

        // UserIdProviderInterface: уже регистрируется в Auth\ModuleServiceProvider
        // (ядерный W3a\Core\Auth\UserIdProvider) — здесь не требуется.

        // =========================================================================
        // 2. РЕГИСТРАЦИЯ ГРУПП MIDDLEWARE
        // =========================================================================
        $this->registerMiddlewareGroups($container);
    }

    /**
     * Регистрирует группы middleware в роутере.
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