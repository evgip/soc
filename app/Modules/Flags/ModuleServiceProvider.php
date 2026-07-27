<?php

declare(strict_types=1);

namespace App\Modules\Flags;

use W3a\Core\Container;
use W3a\Core\Config;
use W3a\Core\Database;
use W3a\Core\Logger;
use W3a\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Flags\Models\Flag;

/**
 * Провайдер сервисов модуля Flags.
 * 
 * Регистрирует модель Flag с инъекцией зависимостей:
 * - Database для работы с БД
 * - Logger для логирования
 * - Config для получения настроек (пороги, причины, кулдауны)
 * 
 * Cross-module зависимости:
 * - Comment (из Stories) — уже зарегистрирован в Stories\ModuleServiceProvider
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // ✅ Регистрируем путь к конфигам модуля
        $config = $container->get(Config::class);
        $config->addModulePath('flags', __DIR__ . '/Config');

        // ✅ Передаём Database, Logger и Config в конструктор модели
        $container->singleton(Flag::class, function (Container $c) {
            return new Flag(
                $c->get(Database::class),
                $c->get(Logger::class),
                $c->get(Config::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}