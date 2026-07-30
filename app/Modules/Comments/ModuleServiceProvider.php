<?php

declare(strict_types=1);

namespace App\Modules\Comments;

use W3a\Core\Foundation\Container;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;
use W3a\Core\Support\Validator;
use W3a\Core\Events\EventDispatcher;
use W3a\Core\Security\UserContext;
use W3a\Core\Events\Listeners\AuditListener;
use W3a\Core\Support\Audit;

use App\Modules\Stories\Listeners\UpdateStoryCommentsCountListener;
use App\Modules\Stories\Models\Story;

use App\Modules\Comments\Events\CommentCreated;
use App\Modules\Comments\Events\CommentUpdated;
use App\Modules\Comments\Events\CommentDeleted;
use App\Modules\Comments\Events\CommentRestored;

use App\Modules\Comments\Models\Comment;
use App\Modules\Comments\Services\CommentService;
use App\Modules\Notifications\Services\NotificationService;

class ModuleServiceProvider extends \W3a\Core\Foundation\ModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛЬ ===
        $container->singleton(Comment::class, function(Container $c) {
            return new Comment(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // === СЕРВИС ===
        $container->singleton(CommentService::class, function (Container $c) {
            return new CommentService(
                $c->get(Comment::class),
                $c->get(Validator::class),
                $c->get(NotificationService::class),
                $c->get(EventDispatcher::class),
                $c->get(UserContext::class)
            );
        });

        // === СЛУШАТЕЛИ ===
        $container->singleton(AuditListener::class, function(Container $c) {
            return new AuditListener(
                $c->get(Audit::class)
            );
        });

        $container->singleton(UpdateStoryCommentsCountListener::class, function(Container $c) {
            return new UpdateStoryCommentsCountListener(
                $c->get(Story::class)
            );
        });
    }

    public function boot(): void
    {
        $dispatcher = $this->container->get(EventDispatcher::class);
        
        $auditListener = $this->container->get(AuditListener::class);
        $commentsCountListener = $this->container->get(UpdateStoryCommentsCountListener::class);
        
        // Аудит событий комментариев
        $dispatcher->listen(CommentCreated::class, [$auditListener, 'handle']);
        $dispatcher->listen(CommentUpdated::class, [$auditListener, 'handle']); 
        $dispatcher->listen(CommentDeleted::class, [$auditListener, 'handle']);
        $dispatcher->listen(CommentRestored::class, [$auditListener, 'handle']);
    }
}