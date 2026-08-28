<?php

declare(strict_types=1);

namespace App\Modules\Votes;

use W3a\Core\Foundation\Container;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;
use W3a\Core\Foundation\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Votes\Models\Vote;
use App\Modules\Votes\Services\VoteService;
use App\Modules\Comments\Models\Comment;
use App\Modules\Stories\Services\RankingService; 

/**
 * Провайдер сервисов модуля Votes.
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        $container->singleton(Vote::class, function (Container $c) {
            return new Vote(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // === СЕРВИСЫ ===
        $container->singleton(VoteService::class, function (Container $c) {
            return new VoteService(
                $c->get(Vote::class),
                $c->get(Comment::class),
                $c->get(Logger::class),
                $c->get(Database::class),
                $c->get(RankingService::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}