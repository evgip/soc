<?php

declare(strict_types=1);

namespace App\Modules\Invitations;

use W3a\Core\Container;
use W3a\Core\Database;
use W3a\Core\Logger;
use W3a\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Invitations\Models\Invitation;
use App\Modules\Invitations\Models\InvitationRequest;

/**
 * Провайдер сервисов модуля Invitations.
 * 
 * Регистрирует модели для работы с приглашениями.
 * Сервисов в модуле нет — только модели.
 * 
 * Cross-module зависимости:
 * - User (из Users) — уже зарегистрирован в Users\ModuleServiceProvider
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // ✅ Передаём Database и Logger в конструкторы моделей
        $container->singleton(Invitation::class, function (Container $c) {
            return new Invitation(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(InvitationRequest::class, function (Container $c) {
            return new InvitationRequest(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}