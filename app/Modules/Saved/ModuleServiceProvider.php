<?php

declare(strict_types=1);

namespace App\Modules\Saved;

use W3a\Core\Foundation\Container;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;
use W3a\Core\Foundation\ModuleServiceProvider as BaseModuleServiceProvider;

use App\Modules\Saved\Models\SavedStory;
use App\Modules\Saved\Services\SavedService;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        $container->singleton(SavedStory::class, function(Container $c) {
            return new SavedStory(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // ✅ Session удалён из зависимостей сервиса
        $container->singleton(SavedService::class, function(Container $c) {
            return new SavedService(
                $c->get(SavedStory::class)
            );
        });
    }
}