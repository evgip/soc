<?php

declare(strict_types=1);

namespace App\Modules\Tags;

use W3a\Core\Container;
use W3a\Core\Database;
use W3a\Core\Logger;
use W3a\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Tags\Models\Category;
use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Models\TagFilter;
use App\Modules\Tags\Models\Tagging;
use App\Modules\Tags\Services\CategoryService;
use App\Modules\Tags\Services\TagFilterService;
use App\Modules\Stories\Models\ReadRibbon;

/**
 * Провайдер сервисов модуля Tags.
 * 
 * Регистрирует модели и сервисы для работы с тегами и категориями.
 * 
 * Cross-module зависимости:
 * - ReadRibbon (из Stories) — уже зарегистрирован в Stories\ModuleServiceProvider
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        // ✅ Передаём Database и Logger в конструкторы моделей
        
        $container->singleton(Category::class, function (Container $c) {
            return new Category(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(Tag::class, function (Container $c) {
            return new Tag(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(TagFilter::class, function (Container $c) {
            return new TagFilter(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(Tagging::class, function (Container $c) {
            return new Tagging(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });
        
        // === СЕРВИСЫ ===
        
        $container->singleton(CategoryService::class, function (Container $c) {
            return new CategoryService(
                $c->get(Category::class),
                $c->get(Tag::class),
                $c->get(TagFilter::class),
                $c->get(ReadRibbon::class)
            );
        });
        
        $container->singleton(TagFilterService::class, function (Container $c) {
            return new TagFilterService(
                $c->get(TagFilter::class),
                $c->get(Tag::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}