<?php

declare(strict_types=1);

namespace App\Modules\Collections;

use W3a\Core\Foundation\ModuleServiceProvider as BaseServiceProvider;
use W3a\Core\Foundation\Container;
use W3a\Core\Foundation\Config;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;
use W3a\Core\Support\Validator;
use W3a\Core\Support\Audit;
use W3a\Core\Events\EventDispatcher;
use W3a\Core\Security\UserContext;
use W3a\Core\Storage\StorageManager;

use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\CollectionItem;
use App\Modules\Collections\Services\CollectionService;
use App\Modules\Collections\Services\CollectionCoverService;
use App\Modules\Stories\Models\Story;

/**
 * Провайдер модуля Collections (серии статей).
 */
class ModuleServiceProvider extends BaseServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        $container->singleton(Collection::class, function (Container $c) {
            return new Collection(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(CollectionItem::class, function (Container $c) {
            return new CollectionItem(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // === СЕРВИС ===
        $container->singleton(CollectionService::class, function (Container $c) {
            return new CollectionService(
                $c->get(Collection::class),
                $c->get(CollectionItem::class),
                $c->get(Story::class),
                $c->get(Validator::class),
                $c->get(EventDispatcher::class),
                $c->get(UserContext::class),
                $c->get(Audit::class)
            );
        });
		
		$container->singleton(CollectionCoverService::class, function (Container $c) {
			return new CollectionCoverService(
				$c->get(StorageManager::class),
				$c->get(Config::class)
			);
		});
    }

    public function boot(): void
    {
        // Инициализация модуля (слушатели событий при необходимости)
    }
}