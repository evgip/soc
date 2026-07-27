<?php

declare(strict_types=1);

namespace App\Modules\Mail;

use W3a\Core\Container;
use W3a\Core\Logger;
use W3a\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Mail\Core\Mailer;

/**
 * Провайдер сервисов модуля Mail.
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // ✅ Mailer: получает Logger через контейнер
        $container->singleton(Mailer::class, function (Container $c) {
            return new Mailer(
                $c->get(Logger::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}