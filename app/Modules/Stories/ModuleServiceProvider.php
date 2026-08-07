<?php

declare(strict_types=1);

namespace App\Modules\Stories;

use W3a\Core\Foundation\Container;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;
use W3a\Core\Support\Audit;
use W3a\Core\Support\HtmlSanitizer;
use W3a\Core\Support\Validator;
use W3a\Core\Events\EventDispatcher;
use W3a\Core\Security\UserContext;
use W3a\Core\Cache\FileCache;

use App\Modules\Stories\Events\StoryCreated;
use App\Modules\Stories\Events\StoryDeleted;
use App\Modules\Stories\Events\StoryRestored;
use App\Modules\Comments\Events\CommentCreated;
use App\Modules\Comments\Events\CommentDeleted;
use App\Modules\Comments\Events\CommentRestored;

use W3a\Core\Events\Listeners\AuditListener;
use App\Modules\Stories\Listeners\UpdateStoryCommentsCountListener;

use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Models\StoryView;
use App\Modules\Stories\Models\ReadRibbon;
use App\Modules\Stories\Services\StoryService;
use App\Modules\Stories\Services\StoryFilterService;
use App\Modules\Stories\Services\ReadRibbonService;
use App\Modules\Stories\Services\StoryValidator;
use App\Modules\Stories\Services\UrlFetcherService;
use App\Modules\Stories\Services\RankingService;
use App\Modules\Stories\Services\RecommendationService;
use App\Modules\Stories\Services\TrendingService;
use App\Modules\Stories\Services\StaffPicksService;
use App\Modules\Tags\Services\TagValidator;
use App\Modules\Muted\Services\MuteService;
use App\Modules\Subscriptions\Services\SubscriptionService;

// 🆕 НОВОЕ: импорт CacheHelper
use App\Modules\Common\Support\CacheHelper;

class ModuleServiceProvider extends \W3a\Core\Foundation\ModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // ================================================================
        // БАЗОВЫЕ СЕРВИСЫ (без зависимостей)
        // ================================================================
        
        $container->singleton(RankingService::class, function(Container $c) {
            return new RankingService();
        });

        $container->singleton(HtmlSanitizer::class, function(Container $c) {
            return new HtmlSanitizer();
        });

        // ================================================================
        // ИНФРАСТРУКТУРА: КЭШ (регистрируем ДО сервисов, которые от него зависят)
        // ================================================================

        // 🆕 Тонкая обёртка над FileCache с удобными методами remember() и forgetMany()
        $container->singleton(CacheHelper::class, function(Container $c) {
            return new CacheHelper($c->get(FileCache::class));
        });

        // ================================================================
        // МОДЕЛИ
        // ================================================================

        $container->singleton(Story::class, function(Container $c) {
            return new Story(
                $c->get(Database::class),
                $c->get(Logger::class),
                $c->get(RankingService::class),
                $c->get(HtmlSanitizer::class)
            );
        });

        $container->singleton(ReadRibbon::class, function(Container $c) {
            return new ReadRibbon(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(StoryView::class, function(Container $c) {
            return new StoryView(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // ================================================================
        // СЕРВИСЫ
        // ================================================================

        $container->singleton(StoryValidator::class, function (Container $c) {
            return new StoryValidator(
                $c->get(TagValidator::class)
            );
        });

        $container->singleton(StoryService::class, function (Container $c) {
            return new StoryService(
                $c->get(Story::class),
                $c->get(StoryValidator::class),
                $c->get(Validator::class),
                $c->get(Audit::class),
                $c->get(EventDispatcher::class),
                $c->get(UserContext::class),
                $c->get(HtmlSanitizer::class)
            );
        });

        $container->singleton(ReadRibbonService::class, function (Container $c) {
            return new ReadRibbonService(
                $c->get(ReadRibbon::class),
                $c->get(UserContext::class)
            );
        });

        $container->singleton(UrlFetcherService::class, function(Container $c) {
            return new UrlFetcherService();
        });

        // ================================================================
        // MEDIUM-СТИЛЬ СЕРВИСЫ (с кэшированием через CacheHelper)
        // ================================================================

        $container->singleton(RecommendationService::class, function(Container $c) {
            return new RecommendationService(
                $c->get(Database::class),
                $c->get(StoryView::class),
                $c->get(SubscriptionService::class)
            );
        });

        $container->singleton(TrendingService::class, function(Container $c) {
            return new TrendingService(
                $c->get(Database::class),
                $c->get(CacheHelper::class)
            );
        });

        $container->singleton(StaffPicksService::class, function(Container $c) {
            return new StaffPicksService(
                $c->get(Database::class),
                $c->get(CacheHelper::class)
            );
        });

		$container->singleton(\App\Modules\Stories\Services\ImageProcessorService::class, function(Container $c) {
			return new \App\Modules\Stories\Services\ImageProcessorService(
				$c->get(Logger::class)
			);
		});

        // ================================================================
        // СЛУШАТЕЛИ СОБЫТИЙ
        // ================================================================

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

        $dispatcher->listen(StoryCreated::class, [$auditListener, 'handle']);
        $dispatcher->listen(StoryDeleted::class, [$auditListener, 'handle']);
        $dispatcher->listen(StoryRestored::class, [$auditListener, 'handle']);

        $dispatcher->listen(CommentCreated::class, [$commentsCountListener, 'handleCreated']);
        $dispatcher->listen(CommentDeleted::class, [$commentsCountListener, 'handleDeleted']);
        $dispatcher->listen(CommentRestored::class, [$commentsCountListener, 'handleRestored']);
    }
}