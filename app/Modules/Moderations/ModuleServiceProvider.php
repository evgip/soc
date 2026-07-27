<?php

declare(strict_types=1);

namespace App\Modules\Moderations;

use W3a\Core\Container;
use W3a\Core\Database;
use W3a\Core\Logger;
use W3a\Core\Audit;
use W3a\Core\Events\EventDispatcher;
use W3a\Core\Events\Listeners\AuditListener;
use W3a\Core\ModuleServiceProvider as BaseModuleServiceProvider;

use App\Modules\Moderations\Events\ModNoteAdded;

use App\Modules\Moderations\Models\ModActivity;
use App\Modules\Moderations\Models\Moderation;
use App\Modules\Moderations\Models\ModNote;
use App\Modules\Moderations\Services\ModerationService;
use App\Modules\Users\Models\User;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        $container->singleton(ModActivity::class, function (Container $c) {
            return new ModActivity($c->get(Database::class), $c->get(Logger::class));
        });

        $container->singleton(Moderation::class, function (Container $c) {
            return new Moderation($c->get(Database::class), $c->get(Logger::class));
        });

        $container->singleton(ModNote::class, function (Container $c) {
            return new ModNote($c->get(Database::class), $c->get(Logger::class));
        });

        $container->singleton(ModerationService::class, function (Container $c) {
            return new ModerationService(
                $c->get(Moderation::class),
                $c->get(ModNote::class),
                $c->get(User::class),
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

        $dispatcher->listen(ModNoteAdded::class, [$auditListener, 'handle']);
    }
}