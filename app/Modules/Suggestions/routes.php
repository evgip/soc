<?php

/**
 * Маршруты модуля Suggestions (предложения изменений контента)
 * 
 * Все маршруты требуют авторизации (middleware: web + auth).
 * 
 * @var \W3a\Core\Http\Router $router
 */

use App\Modules\Suggestions\Controllers\SuggestionController;

// =========================================================================
// МАРШРУТЫ ДЛЯ АВТОРИЗОВАННЫХ ПОЛЬЗОВАТЕЛЕЙ
// =========================================================================


$router->group(['middleware' => ['web', 'auth']], function ($router) {

    $router->add('GET', '/suggestions/{targetType}/{targetId}', SuggestionController::class . '@index', 'suggestions.index');
    $router->add('GET', '/suggestions/{targetType}/{targetId}/log', SuggestionController::class . '@log', 'suggestions.log');
    $router->add('POST', '/suggestions', SuggestionController::class . '@store', 'suggestions.store');
    $router->add('POST', '/suggestions/{id}/support', SuggestionController::class . '@support', 'suggestions.support');

    // маршруты для модераторов
    $router->group(['middleware' => ['moderator']], function ($router) {
        $router->add('POST', '/suggestions/{id}/approve', SuggestionController::class . '@approve', 'suggestions.approve');
        $router->add('POST', '/suggestions/{id}/reject', SuggestionController::class . '@reject', 'suggestions.reject');
    });
});
