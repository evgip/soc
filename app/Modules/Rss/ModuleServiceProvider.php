<?php

declare(strict_types=1);

namespace App\Modules\Rss;

use W3a\Core\Foundation\Container;
use App\Modules\Rss\Services\RssService;

class ModuleServiceProvider extends \W3a\Core\Foundation\ModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        $container->singleton(RssService::class, function (Container $c) {
            return new RssService();
        });
    }
}
