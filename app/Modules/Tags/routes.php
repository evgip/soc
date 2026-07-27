<?php
/**
 * Маршруты модуля Tags (теги, фильтры и категории)
 * 
 * Две группы доступа:
 * - Публичные: просмотр тегов и категорий
 * - Авторизованные: персональные фильтры пользователя
 * 
 * @var \W3a\Core\Router $router
 */

use App\Modules\Tags\Controllers\TagsController;

// =========================================================================
// ПУБЛИЧНЫЕ МАРШРУТЫ (доступны всем)
// =========================================================================

/**
 * Список всех тегов сайта.
 */
$router->add(
    'GET', 
    '/tags', 
    TagsController::class . '@index', 
    'tags.index'
);

/**
 * Страница конкретной категории.
 * 
 * @param string $slug URL-имя категории
 */
$router->add(
    'GET', 
    '/categories/{slug}', 
    TagsController::class . '@categoriesShow', 
    'categories.show'
);

// =========================================================================
// МАРШРУТЫ ДЛЯ АВТОРИЗОВАННЫХ (персональные фильтры)
// =========================================================================

$router->group(['middleware' => ['web', 'auth']], function($router) {
    
    /**
     * Страница управления персональными фильтрами.
     */
    $router->add(
        'GET', 
        '/filters', 
        TagsController::class . '@filters', 
        'tags.filters'
    );
    
    /**
     * AJAX: добавить тег в персональные фильтры.
     */
    $router->add(
        'POST', 
        '/filters/add', 
        TagsController::class . '@addFilter', 
        'tags.filters.add'
    );
    
    /**
     * AJAX: удалить тег из персональных фильтров.
     */
    $router->add(
        'POST', 
        '/filters/remove', 
        TagsController::class . '@removeFilter', 
        'tags.filters.remove'
    );
});