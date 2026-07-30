<?php
/**
 * Маршруты модуля Flags (жалобы/флаги на контент)
 * 
 * Две группы доступа:
 * - Авторизованные пользователи: создание жалоб
 * - Администраторы: управление жалобами
 * 
 * @var \W3a\Core\Http\Router $router
 */

use App\Modules\Flags\Controllers\FlagsController;

// =========================================================================
// МАРШРУТЫ ДЛЯ АВТОРИЗОВАННЫХ ПОЛЬЗОВАТЕЛЕЙ (создание жалоб)
// =========================================================================

$router->group(['middleware' => ['web', 'auth']], function($router) {
    
    /**
     * Форма подачи жалобы на контент.
     * 
     * @param string $type Тип сущности: 'story', 'comment', 'user'
     * @param int    $id   ID сущности
     * 
     * @example GET /flags/report/story/123    → жалоба на историю
     * @example GET /flags/report/comment/456  → жалоба на комментарий
     * @example GET /flags/report/user/789     → жалоба на пользователя
     */
    $router->add(
        'GET', 
        '/flags/report/{type}/{id}', 
        FlagsController::class . '@reportForm', 
        'flags.report'
    );
    
    /**
     * Отправка жалобы (обработка формы).
     * Данные сущности передаются в скрытых полях формы.
     */
    $router->add(
        'POST', 
        '/flags/report', 
        FlagsController::class . '@submit', 
        'flags.submit'
    );
});

// =========================================================================
// АДМИН-ПАНЕЛЬ (управление жалобами)
// =========================================================================

$router->group(['middleware' => ['web', 'admin'], 'prefix' => '/admin'], function($router) {
    
    /**
     * Список всех жалоб (ожидает рассмотрения / обработанные).
     */
    $router->add(
        'GET', 
        '/flags', 
        FlagsController::class . '@adminIndex', 
        'admin.flags'
    );
    
    /**
     * API: количество жалоб, ожидающих рассмотрения.
     * Используется для badge в админ-меню.
     * 
     * ⚠️ ВАЖНО: этот маршрут ДОЛЖЕН идти ДО /flags/{id}/...,
     * иначе 'count' будет интерпретирован как {id}.
     */
    $router->add(
        'GET', 
        '/flags/count', 
        FlagsController::class . '@pendingCount', 
        'admin.flags.count'
    );
    
    /**
     * Разрешение жалобы (принять/отклонить).
     */
    $router->add(
        'POST', 
        '/flags/{id}/resolve', 
        FlagsController::class . '@resolve', 
        'admin.flags.resolve'
    );
});