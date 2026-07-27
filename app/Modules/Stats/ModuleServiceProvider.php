<?php

declare(strict_types=1);

namespace App\Modules\Stats;

use W3a\Core\Container;
use W3a\Core\Database;
use W3a\Core\Logger;
use W3a\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Stats\Models\Stats;

/**
 * Провайдер сервисов модуля Stats.
 * 
 * Регистрирует модель для работы со статистикой.
 * Сервисов в модуле нет — только модель.
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // ✅ Передаём Database и Logger в конструктор модели
        $container->singleton(Stats::class, function (Container $c) {
            return new Stats(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}