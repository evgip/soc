<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use W3a\Core\Container;
use W3a\Core\Logger;
use W3a\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Muted\Services\MuteService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Comments\Models\Comment;
use App\Modules\Users\Models\User;

/**
 * Провайдер сервисов модуля Notifications.
 * 
 * Не дублирует регистрацию моделей из других модулей.
 * Модели Notification, User, Comment уже зарегистрированы в своих модулях.
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === СЕРВИСЫ ===
        // Все зависимости уже зарегистрированы в других модулях:
        // - Notification → Users/ModuleServiceProvider
        // - Comment → Stories/ModuleServiceProvider
        // - User → Users/ModuleServiceProvider
        $container->singleton(NotificationService::class, function (Container $c) {
            return new NotificationService(
                $c->get(\App\Modules\Notifications\Models\Notification::class),
                $c->get(Comment::class),
                $c->get(User::class),
                $c->get(Logger::class),
				$c->get(MuteService::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}
