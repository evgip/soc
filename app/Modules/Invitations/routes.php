<?php
/**
 * Маршруты модуля Invitations (система приглашений)
 * 
 * Четыре группы доступа:
 * - Гости: регистрация по приглашению
 * - Авторизованные: управление своими приглашениями
 * - Публичные: запрос приглашения
 * - Администраторы: модерация запросов
 * 
 * @var \W3a\Core\Router $router
 */

use App\Modules\Invitations\Controllers\InvitationsController;

// =========================================================================
// ДЛЯ ГОСТЕЙ — регистрация по приглашению
// =========================================================================

$router->group(['middleware' => ['web', 'guest']], function($router) {
    
    /**
     * Форма регистрации по коду приглашения.
     * 
     * @param string $code Уникальный код приглашения
     */
    $router->add(
        'GET', 
        '/register/invite/{code}', 
        InvitationsController::class . '@showInviteRegistration', 
        'invitations.register.form'
    );
    
    /**
     * Обработка регистрации по приглашению.
     */
    $router->add(
        'POST', 
        '/register/invite/{code}', 
        InvitationsController::class . '@registerWithInvite', 
        'invitations.register.submit'
    );
});

// =========================================================================
// ДЛЯ АВТОРИЗОВАННЫХ — управление приглашениями
// =========================================================================

$router->group(['middleware' => ['web', 'auth']], function($router) {
    
    /**
     * Список приглашений пользователя (созданные, использованные, отозванные).
     */
    $router->add(
        'GET', 
        '/invitations', 
        InvitationsController::class . '@index', 
        'invitations.index'
    );
    
    /**
     * Создание нового приглашения.
     */
    $router->add(
        'POST', 
        '/invitations/create', 
        InvitationsController::class . '@create', 
        'invitations.create'
    );
    
    /**
     * Отзыв неиспользованного приглашения.
     * 
     * @param int $id ID приглашения
     */
    $router->add(
        'POST', 
        '/invitations/revoke/{id}', 
        InvitationsController::class . '@revoke', 
        'invitations.revoke'
    );
});

// =========================================================================
// ПУБЛИЧНЫЕ — запрос приглашения (доступны всем)
// =========================================================================

/**
 * Форма запроса приглашения для незарегистрированных.
 */
$router->add(
    'GET', 
    '/invite/request', 
    InvitationsController::class . '@showRequestForm', 
    'invitations.request.form'
);

/**
 * Отправка запроса на приглашение.
 */
$router->add(
    'POST', 
    '/invite/request', 
    InvitationsController::class . '@submitRequest', 
    'invitations.request.submit'
);

// =========================================================================
// АДМИН-ПАНЕЛЬ — модерация запросов приглашений
// =========================================================================

$router->group(['middleware' => ['web', 'admin'], 'prefix' => '/admin/invitations'], function($router) {
    
    /**
     * Список запросов на приглашения (ожидают рассмотрения).
     */
    $router->add(
        'GET', 
        '/requests', 
        InvitationsController::class . '@adminRequests', 
        'admin.invitations.requests'
    );
    
    /**
     * Одобрение запроса на приглашение.
     * 
     * @param int $id ID запроса
     */
    $router->add(
        'POST', 
        '/approve/{id}', 
        InvitationsController::class . '@approveRequest', 
        'admin.invitations.approve'
    );
    
    /**
     * Отклонение запроса на приглашение.
     * 
     * @param int $id ID запроса
     */
    $router->add(
        'POST', 
        '/reject/{id}', 
        InvitationsController::class . '@rejectRequest', 
        'admin.invitations.reject'
    );
});