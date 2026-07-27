<?php
/**
 * Маршруты модуля Messages (личные сообщения)
 * 
 * Все маршруты требуют авторизации (middleware: web + auth).
 * 
 * @var \W3a\Core\Router $router
 */

use App\Modules\Messages\Controllers\MessagesController;

// =========================================================================
// МАРШРУТЫ ДЛЯ АВТОРИЗОВАННЫХ ПОЛЬЗОВАТЕЛЕЙ
// =========================================================================

$router->group(['middleware' => ['web', 'auth']], function($router) {
    
    // -------------------------------------------------------------------------
    // ПРОСМОТР СООБЩЕНИЙ
    // -------------------------------------------------------------------------
    
    /**
     * Список всех диалогов пользователя.
     */
    $router->add(
        'GET', 
        '/messages', 
        MessagesController::class . '@index', 
        'messages.index'
    );
    
    /**
     * Просмотр конкретного диалога.
     * 
     * @param int $id ID диалога (thread)
     */
    $router->add(
        'GET', 
        '/messages/chat/{id}', 
        MessagesController::class . '@showDialog', 
        'messages.dialog'
    );
    
    // -------------------------------------------------------------------------
    // ДЕЙСТВИЯ С СООБЩЕНИЯМИ
    // -------------------------------------------------------------------------
    
    /**
     * Отправка сообщения в существующий диалог.
     * ID диалога передаётся в скрытом поле формы.
     */
    $router->add(
        'POST', 
        '/messages/send', 
        MessagesController::class . '@sendMessage', 
        'messages.send.submit'
    );
    
    /**
     * Создание нового диалога или переход в существующий.
     * Вызывается с профиля пользователя по кнопке "Написать".
     * 
     * @param int $userId ID пользователя, которому пишем
     */
    $router->add(
        'POST', 
        '/messages/start/{userId}', 
        MessagesController::class . '@startConversation', 
        'messages.start'
    );
});
 