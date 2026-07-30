<?php
/**
 * Маршруты модуля Origins (управление доменами)
 * 
 * Две группы доступа:
 * - Публичные: список забаненных доменов (для всех)
 * - Админка: управление банами доменов (модераторы/админы)
 * 
 * @var \W3a\Core\Http\Router $router
 */

use App\Modules\Origins\Controllers\OriginsController;

// =========================================================================
// ПУБЛИЧНЫЕ МАРШРУТЫ (доступны всем)
// =========================================================================

/**
 * Публичная страница списка забаненных доменов.
 */
$router->add(
    'GET', 
    '/domains', 
    OriginsController::class . '@index', 
    'domains.index'
);

// =========================================================================
// АДМИН-ПАНЕЛЬ (модераторы и админы, префикс /admin/domains)
// =========================================================================

$router->group(['middleware' => ['web', 'moderator'], 'prefix' => '/admin/domains'], function($router) {
    
    /**
     * Список всех доменов (админка).
     */
    $router->add(
        'GET', 
        '', 
        OriginsController::class . '@adminIndex', 
        'admin.domains'
    );
    
    /**
     * Форма бана домена.
     */
    $router->add(
        'GET', 
        '/create', 
        OriginsController::class . '@showBanForm', 
        'admin.domains.create'
    );
    
    /**
     * Обработка бана домена.
     */
    $router->add(
        'POST', 
        '/ban', 
        OriginsController::class . '@ban', 
        'admin.domains.ban'
    );
    
    /**
     * Разбан домена.
     * 
     * @param int $id ID домена
     */
    $router->add(
        'POST', 
        '/{id}/unban', 
        OriginsController::class . '@unban', 
        'admin.domains.unban'
    );
});