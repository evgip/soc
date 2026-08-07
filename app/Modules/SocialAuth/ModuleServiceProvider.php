<?php

declare(strict_types=1);

namespace App\Modules\SocialAuth;

use W3a\Core\Foundation\Container;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;
use W3a\Core\Events\EventDispatcher;

use App\Modules\SocialAuth\Models\SocialAccount;
use App\Modules\SocialAuth\Services\SocialAuthService;
use App\Modules\SocialAuth\Services\YandexOAuthService;
use App\Modules\SocialAuth\Services\VKOAuthService;
use App\Modules\Users\Models\User;

class ModuleServiceProvider extends \W3a\Core\Foundation\ModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        $container->singleton(SocialAccount::class, function (Container $c) {
            return new SocialAccount(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(YandexOAuthService::class, function (Container $c) {
            return new YandexOAuthService($c->get(Logger::class));
        });

        $container->singleton(VKOAuthService::class, function (Container $c) {
            return new VKOAuthService($c->get(Logger::class));
        });

        $container->singleton(SocialAuthService::class, function (Container $c) {
            return new SocialAuthService(
                $c->get(Logger::class),
                $c->get(SocialAccount::class),
                $c->get(User::class),
                $c->get(EventDispatcher::class)
            );
        });
    }

    public function boot(): void
    {
        // Здесь можно зарегистрировать слушателей событий
        // $dispatcher = $this->container->get(EventDispatcher::class);
        // $dispatcher->listen(SocialUserCreated::class, [SomeListener::class, 'handle']);
    }
}