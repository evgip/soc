<?php
/**
 * Маршруты модуля Votes
 * 
 * Единый endpoint для голосования за истории и комментарии.
 * 
 * @var \W3a\Core\Http\Router $router
 */

use App\Modules\Votes\Controllers\VotesController;

// =========================================================================
// МАРШРУТЫ ДЛЯ АВТОРИЗОВАННЫХ ПОЛЬЗОВАТЕЛЕЙ
// =========================================================================

$router->group(['middleware' => ['web', 'auth']], function($router) {
    
    /**
     * Универсальный endpoint для голосования
     * 
     * @param string $type      Тип объекта: 'story' или 'comment'
     * @param int    $id        ID объекта
     * @param string $direction Направление: 'up' или 'down'
     * 
     * @example POST /vote/story/123/up    → голос за историю
     * @example POST /vote/comment/456/down → голос против комментария
     */
    $router->add(
        'POST', 
        '/vote/{type}/{id}/{direction}', 
        VotesController::class . '@handle', 
        'votes.toggle'
    );
});