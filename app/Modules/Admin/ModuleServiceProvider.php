<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use W3a\Core\Container;
use W3a\Core\Database;
use W3a\Core\Logger;
use W3a\Core\Audit;
use W3a\Core\Validator;
use W3a\Core\ModuleServiceProvider as BaseModuleServiceProvider;

use App\Modules\Admin\Models\AdminUser;
use App\Modules\Admin\Models\AuditLog;
use App\Modules\Admin\Models\Audit as AdminAuditModel;
use App\Modules\Admin\Services\AdminUserService;
use App\Modules\Admin\Services\AdminTagService;
use App\Modules\Admin\Services\AdminCategoryService;
use App\Modules\Admin\Services\AdminAuditService;
use App\Modules\Admin\Services\AdminToolsService;
use App\Modules\Admin\Services\AdminFirewallService;
use App\Modules\Admin\Services\AdminInvitationService;

use App\Modules\Users\Models\User;
use App\Modules\Users\Models\Notification;
use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Models\Category;
use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Services\RankingService; 
use App\Modules\Comments\Models\Comment;
use App\Modules\Invitations\Models\InvitationRequest;
use App\Modules\Mail\Core\Mailer;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        $container->singleton(AdminUser::class, function (Container $c) {
            return new AdminUser($c->get(Database::class), $c->get(Logger::class));
        });

        $container->singleton(AuditLog::class, function (Container $c) {
            return new AuditLog($c->get(Database::class), $c->get(Logger::class));
        });

        $container->singleton(AdminAuditModel::class, function (Container $c) {
            return new AdminAuditModel($c->get(Database::class), $c->get(Logger::class));
        });

        $container->singleton(InvitationRequest::class, function (Container $c) {
            return new InvitationRequest($c->get(Database::class), $c->get(Logger::class));
        });

        // === СЕРВИСЫ ===

        $container->singleton(AdminUserService::class, function (Container $c) {
            return new AdminUserService(
                $c->get(User::class),
                $c->get(AdminUser::class),
                $c->get(Notification::class),
                $c->get(Audit::class)
            );
        });

        $container->singleton(AdminTagService::class, function (Container $c) {
            return new AdminTagService(
                $c->get(Tag::class),
                $c->get(Category::class),
                $c->get(Story::class),
                $c->get(Audit::class),
                $c->get(Validator::class),
                $c->get(Database::class)
            );
        });

        $container->singleton(AdminCategoryService::class, function (Container $c) {
            return new AdminCategoryService(
                $c->get(Category::class),
                $c->get(Audit::class)
            );
        });

        $container->singleton(AdminAuditService::class, function (Container $c) {
            return new AdminAuditService(
                $c->get(AuditLog::class),
                $c->get(AdminAuditModel::class),
                $c->get(Database::class)
            );
        });

        $container->singleton(AdminToolsService::class, function (Container $c) {
            return new AdminToolsService(
                $c->get(Audit::class),
                $c->get(Logger::class),
                $c->get(Comment::class),
                $c->get(Mailer::class),
                $c->get(RankingService::class)
            );
        });

        $container->singleton(AdminFirewallService::class, function (Container $c) {
            return new AdminFirewallService(
                $c->get(Database::class),
                $c->get(Audit::class)
            );
        });

        $container->singleton(AdminInvitationService::class, function (Container $c) {
            return new AdminInvitationService(
                $c->get(InvitationRequest::class)
            );
        });
    }
}