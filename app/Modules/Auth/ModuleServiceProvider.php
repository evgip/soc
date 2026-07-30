<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use W3a\Core\Foundation\Container;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;
use W3a\Core\Http\Session;
use W3a\Core\Support\Audit;
use W3a\Core\Foundation\Config;
use W3a\Core\Http\Request;
use W3a\Core\Foundation\ModuleServiceProvider as BaseModuleServiceProvider;

use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\PasswordResetService;

use App\Modules\Auth\Models\RememberToken;
use App\Modules\Auth\Models\EmailActivation;
use App\Modules\Auth\Models\PasswordResetToken;
use App\Modules\Auth\Models\AuthAttempt;

use App\Modules\Users\Models\User;
use App\Modules\Mail\Core\Mailer;

/**
 * Провайдер сервисов модуля Auth.
 * 
 * Регистрирует модели и сервисы, необходимые для:
 * - Аутентификации пользователей (вход/выход)
 * - Регистрации новых аккаунтов с активацией по email
 * - Восстановления пароля через email
 * - Защиты от брутфорс-атак (блокировка по IP и email)
 * - Функции "Запомнить меня" через безопасные токены
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        
        // Модель для работы с токенами "Запомнить меня"
        $container->singleton(RememberToken::class, function (Container $c) {
            return new RememberToken(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // Модель для работы с токенами активации аккаунта
        $container->singleton(EmailActivation::class, function (Container $c) {
            return new EmailActivation(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // Модель для работы с токенами восстановления пароля
        $container->singleton(PasswordResetToken::class, function (Container $c) {
            return new PasswordResetToken(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // Модель для работы с попытками аутентификации (защита от брутфорса)
        $container->singleton(AuthAttempt::class, function (Container $c) {
            return new AuthAttempt(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // === СЕРВИСЫ ===
        
        // Основной сервис аутентификации
        $container->singleton(AuthService::class, function (Container $c) {
            return new AuthService(
                $c->get(User::class),
                $c->get(RememberToken::class),
                $c->get(EmailActivation::class),
                $c->get(AuthAttempt::class),
                $c->get(Logger::class),
                $c->get(Session::class),
                $c->get(Audit::class),
                $c->get(Mailer::class),
                $c->get(Config::class),
                $c->get(Request::class)
            );
        });

        // Сервис восстановления пароля
        $container->singleton(PasswordResetService::class, function (Container $c) {
            return new PasswordResetService(
                $c->get(User::class),
                $c->get(PasswordResetToken::class),
                $c->get(Mailer::class)
            );
        });
    }

    public function boot(): void
    {
        // Модуль Auth не генерирует событий, которые нужно слушать,
        // поэтому метод boot остаётся пустым.
    }
}