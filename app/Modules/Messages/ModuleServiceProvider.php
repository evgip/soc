<?php

declare(strict_types=1);

namespace App\Modules\Messages;

use W3a\Core\Container;
use W3a\Core\Database;
use W3a\Core\Logger;
use W3a\Core\ModuleServiceProvider as BaseModuleServiceProvider;

use App\Modules\Messages\Models\Conversation;
use App\Modules\Messages\Models\Message;
use App\Modules\Messages\Services\ConversationService;
use App\Modules\Messages\Services\MessageService;
use App\Modules\Users\Models\User;
use App\Modules\Notifications\Services\NotificationService;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        $container->singleton(Conversation::class, function(Container $c) {
            return new Conversation(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });
        
        $container->singleton(Message::class, function(Container $c) {
            return new Message(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });
        
        // === СЕРВИСЫ ===
        $container->singleton(ConversationService::class, function (Container $c) {
            return new ConversationService(
                $c->get(Conversation::class),
                $c->get(User::class)
                // Session удалён из зависимостей
            );
        });

        $container->singleton(MessageService::class, function (Container $c) {
            return new MessageService(
                $c->get(Message::class),
                $c->get(Conversation::class),
                $c->get(NotificationService::class)
            );
        });
    }
}