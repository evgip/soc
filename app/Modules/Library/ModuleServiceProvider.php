<?php

declare(strict_types=1);

namespace App\Modules\Library;

use W3a\Core\Foundation\Container;
use W3a\Core\Foundation\ModuleServiceProvider as BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);
    }
}