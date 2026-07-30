<?php

declare(strict_types=1);

namespace App\Modules\Wiki;

use W3a\Core\Foundation\Container;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;
use W3a\Core\Support\Audit;
use W3a\Core\Events\EventDispatcher;
use W3a\Core\Events\Listeners\AuditListener;
use W3a\Core\Security\UserContext;
use W3a\Core\Foundation\ModuleServiceProvider as BaseModuleServiceProvider;

use App\Modules\Wiki\Events\WikiPageCreated;
use App\Modules\Wiki\Events\WikiPageUpdated;
use App\Modules\Wiki\Events\WikiPageDeleted;

use App\Modules\Wiki\Models\WikiPage;
use App\Modules\Wiki\Models\WikiRevision;
use App\Modules\Wiki\Models\WikiPermission;
use App\Modules\Wiki\Services\WikiService;
use App\Modules\Wiki\Services\WikiPermissionService;
use App\Modules\Tags\Models\Tag;
use App\Modules\Users\Models\User;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        $container->singleton(WikiPage::class, function (Container $c) {
            return new WikiPage($c->get(Database::class), $c->get(Logger::class));
        });

        $container->singleton(WikiRevision::class, function (Container $c) {
            return new WikiRevision($c->get(Database::class), $c->get(Logger::class));
        });

        $container->singleton(WikiPermission::class, function (Container $c) {
            return new WikiPermission($c->get(Database::class), $c->get(Logger::class));
        });

        $container->singleton(WikiPermissionService::class, function (Container $c) {
            return new WikiPermissionService(
                $c->get(WikiPermission::class),
                $c->get(Tag::class),
                $c->get(User::class),
                $c->get(Audit::class),
                $c->get(UserContext::class)
            );
        });

        $container->singleton(WikiService::class, function (Container $c) {
            return new WikiService(
                $c->get(WikiPage::class),
                $c->get(WikiRevision::class),
                $c->get(Audit::class),
                $c->get(EventDispatcher::class)
            );
        });

        $container->singleton(AuditListener::class, function (Container $c) {
            return new AuditListener($c->get(Audit::class));
        });
    }

    public function boot(): void
    {
        $dispatcher = $this->container->get(EventDispatcher::class);
        $auditListener = $this->container->get(AuditListener::class);

        $dispatcher->listen(WikiPageCreated::class, [$auditListener, 'handle']);
        $dispatcher->listen(WikiPageUpdated::class, [$auditListener, 'handle']);
        $dispatcher->listen(WikiPageDeleted::class, [$auditListener, 'handle']);
    }
}