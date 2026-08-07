<?php
/**
 * Маршруты модуля Users (пользователи, авторизация, профиль)
 * 
 * Четыре группы доступа:
 * - Публичные: профили, восстановление пароля, активация
 * - Гости: вход и регистрация (авторизованные редиректятся на главную)
 * - Авторизованные: настройки, выход, управление аккаунтом
 * 
 * @var \W3a\Core\Http\Router $router
 */

use App\Modules\Users\Controllers\UsersController;

// =========================================================================
// ПУБЛИЧНЫЕ МАРШРУТЫ (доступны всем, включая гостей)
// =========================================================================

/**
 * Список пользователей (публичный каталог).
 */
$router->add(
    'GET', 
    '/users', 
    UsersController::class . '@index', 
    'users.index'
);

/**
 * Публичный профиль пользователя.
 * 
 * @param string $username URL-имя пользователя
 */
$router->add(
    'GET', 
    '/@{username}', 
    UsersController::class . '@profile', 
    'user.profile'
);

// =========================================================================
// МАРШРУТЫ ДЛЯ АВТОРИЗОВАННЫХ ПОЛЬЗОВАТЕЛЕЙ
// =========================================================================

$router->group(['middleware' => ['web', 'auth']], function($router) {
    
    // -------------------------------------------------------------------------
    // Настройки аккаунта
    // -------------------------------------------------------------------------
    
    /**
     * Страница настроек аккаунта.
     */
    $router->add(
        'GET', 
        '/account/settings', 
        UsersController::class . '@settings', 
        'account.settings'
    );
    
    /**
     * Обновление основных настроек (email, имя и т.д.).
     */
    $router->add(
        'POST', 
        '/account/settings', 
        UsersController::class . '@updateSettings', 
        'account.settings.submit'
    );
    
    /**
     * Смена пароля.
     */
    $router->add(
        'POST', 
        '/account/settings/password', 
        UsersController::class . '@updatePassword', 
        'account.password.submit'
    );
});