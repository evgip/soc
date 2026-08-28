<?php

declare(strict_types=1);

namespace App\Modules\AuthorStats;

use W3a\Core\Foundation\Container;
use W3a\Core\Foundation\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\AuthorStats\Models\AuthorStatsModel;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        $container->singleton(AuthorStatsModel::class, function ($c) {
            return new AuthorStatsModel($c->get(\W3a\Core\Database\Database::class));
        });
    }
}