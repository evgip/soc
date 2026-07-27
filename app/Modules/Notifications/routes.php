<?php

/**
 * Маршруты модуля Notifications
 * 
 * Все маршруты требуют авторизации (middleware: web + auth).
 * 
 * ВАЖНО: конкретные маршруты (mark-all-read) регистрируются ДО
 * параметрических ({id}/read), чтобы избежать конфликта.
 * 
 * @var \W3a\Core\Router $router
 */

use App\Modules\Notifications\Controllers\NotificationsController;

// =========================================================================
// ВСЕ МАРШРУТЫ ТРЕБУЮТ АВТОРИЗАЦИИ
// =========================================================================

$router->group(['middleware' => ['web', 'auth']], function ($router) {

    // -------------------------------------------------------------------------
    // СТРАНИЦА СПИСКА УВЕДОМЛЕНИЙ
    // -------------------------------------------------------------------------
    $router->add(
        'GET',
        '/notifications',
        NotificationsController::class . '@index',
        'notifications.index'
    );

    // -------------------------------------------------------------------------
    // API-ENDPOINTS (для AJAX)
    // -------------------------------------------------------------------------
    $router->add(
        'GET',
        '/api/notifications/unread',
        NotificationsController::class . '@getUnread',
        'notifications.api.unread'
    );

    $router->add(
        'GET',
        '/api/notifications/count',
        NotificationsController::class . '@getCount',
        'notifications.api.count'
    );

    // -------------------------------------------------------------------------
    // ДЕЙСТВИЯ С УВЕДОМЛЕНИЯМИ
    // ⚠️ ВАЖНО: конкретный маршрут СНАЧАЛА, параметрический — ПОТОМ!
    // -------------------------------------------------------------------------

    // Конкретный маршрут (без параметров)
    $router->add(
        'POST',
        '/notifications/mark-all-read',
        NotificationsController::class . '@markAllAsRead',
        'notifications.markAllRead'
    );

    // Параметрический маршрут (с {id})
    $router->add(
        'POST',
        '/notifications/{id}/read',
        NotificationsController::class . '@markAsRead',
        'notifications.read'
    );
});
