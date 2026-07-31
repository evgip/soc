<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use W3a\Core\Foundation\Container;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;
use W3a\Core\Support\Audit;
use W3a\Core\Foundation\Config;
use W3a\Core\Http\Request;
use W3a\Core\Http\Session;
use W3a\Core\Foundation\ModuleServiceProvider as BaseModuleServiceProvider;

// Модели токенов из ядра
use W3a\Core\Auth\Models\RememberToken;
use W3a\Core\Auth\Models\EmailActivation;
use W3a\Core\Auth\Models\PasswordResetToken;

// Базовые сервисы из ядра
use W3a\Core\Auth\AuthService as BaseAuthService;
use W3a\Core\Auth\PasswordResetService as BasePasswordResetService;
use W3a\Core\Contracts\UserIdProviderInterface;
use W3a\Core\Auth\UserIdProvider;

// Специфика приложения
use App\Modules\Auth\Services\AppAuthService;
use App\Modules\Auth\Services\AppPasswordResetService;
use App\Modules\Users\Models\User;
use App\Modules\Mail\Core\Mailer;

/**
 * Провайдер сервисов модуля Auth.
 * 
 * Связывает модели ядра (RememberToken, EmailActivation, PasswordResetToken)
 * и базовые сервисы (AuthService, PasswordResetService) с конкретными
 * реализациями приложения (AppAuthService, AppPasswordResetService).
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // =========================================================================
        // 1. МОДЕЛЬ ПОЛЬЗОВАТЕЛЯ (из модуля Users)
        // =========================================================================
        $container->singleton(User::class, function (Container $c) {
            return new User(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // =========================================================================
        // 2. МОДЕЛИ ТОКЕНОВ (из ядра, регистрируем здесь)
        // =========================================================================
        
        // Токены "Запомнить меня"
        $container->singleton(RememberToken::class, function (Container $c) {
            return new RememberToken(
                $c->get(Database::class),
                $c->get(Config::class)
            );
        });

        // Токены активации email
        $container->singleton(EmailActivation::class, function (Container $c) {
            return new EmailActivation(
                $c->get(Database::class),
                $c->get(Config::class)
            );
        });

        // Токены восстановления пароля
        $container->singleton(PasswordResetToken::class, function (Container $c) {
            return new PasswordResetToken(
                $c->get(Database::class),
                $c->get(Config::class)
            );
        });

        // =========================================================================
        // 3. СЕРВИСЫ (связываем базовые классы ядра с реализациями приложения)
        // =========================================================================
        
        // Основной сервис аутентификации
        $container->singleton(BaseAuthService::class, function (Container $c) {
            return new AppAuthService(
                $c->get(User::class),
                $c->get(RememberToken::class),
                $c->get(EmailActivation::class),
                $c->get(Logger::class),
                $c->get(Session::class),
                $c->get(Audit::class),
                $c->get(Config::class),
                $c->get(Request::class)
            );
        });

        // Сервис восстановления пароля
        $container->singleton(BasePasswordResetService::class, function (Container $c) {
            return new AppPasswordResetService(
                $c->get(User::class),
                $c->get(PasswordResetToken::class),
                $c->get(Mailer::class)
            );
        });

        // =========================================================================
        // 4. ПРОВАЙДЕР ID (реализация интерфейса ядра)
        // =========================================================================
        $container->singleton(UserIdProviderInterface::class, fn() => new UserIdProvider());
    }

    public function boot(): void
    {
        // Модуль Auth не генерирует событий
    }
}