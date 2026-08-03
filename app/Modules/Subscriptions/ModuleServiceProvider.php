<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions;

use W3a\Core\Foundation\Container;
use W3a\Core\Contracts\DatabaseInterface;
use App\Modules\Subscriptions\Models\FollowedUser;
use App\Modules\Subscriptions\Models\FollowedTag;
use App\Modules\Subscriptions\Services\SubscriptionService;

class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(FollowedUser::class, function (Container $c) {
            return new FollowedUser($c->get(DatabaseInterface::class));
        });

        $container->singleton(FollowedTag::class, function (Container $c) {
            return new FollowedTag($c->get(DatabaseInterface::class));
        });

        $container->singleton(SubscriptionService::class, function (Container $c) {
            return new SubscriptionService(
                $c->get(FollowedUser::class),
                $c->get(FollowedTag::class)
            );
        });
    }
}
