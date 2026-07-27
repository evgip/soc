<?php

declare(strict_types=1);

namespace App\Modules\Muted;

use W3a\Core\Container;
use W3a\Core\Database;
use W3a\Core\Logger;
use W3a\Core\ModuleServiceProvider as BaseModuleServiceProvider;

use App\Modules\Muted\Models\MutedUser;
use App\Modules\Muted\Services\MuteService;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        $container->singleton(MutedUser::class, function (Container $c) {
            return new MutedUser(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(MuteService::class, function (Container $c) {
            return new MuteService(
                $c->get(MutedUser::class)
            );
        });
    }
}