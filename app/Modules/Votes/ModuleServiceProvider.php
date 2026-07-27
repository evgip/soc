<?php

declare(strict_types=1);

namespace App\Modules\Votes;

use W3a\Core\Container;
use W3a\Core\Database;
use W3a\Core\Logger;
use W3a\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Votes\Models\Vote;
use App\Modules\Votes\Services\VoteService;
use App\Modules\Users\Models\User;
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
                $c->get(User::class),
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