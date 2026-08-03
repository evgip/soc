<?php

/**
 * Маршруты модуля Stories
 * 
 * @var \W3a\Core\Http\Router $router
 */

use App\Modules\Stories\Controllers\StoriesController;

// =========================================================================
// ПУБЛИЧНЫЕ МАРШРУТЫ (доступны всем)
// =========================================================================

// Главная страница - лента историй
$router->add('GET', '/', StoriesController::class . '@index', 'home');
$router->add('GET', '/hot', StoriesController::class . '@index', 'stories.hot');
$router->add('GET', '/new', StoriesController::class . '@index', 'stories.new');
$router->add('GET', '/top', StoriesController::class . '@index', 'stories.top');

// Просмотр конкретной истории и комментариев
$router->add('GET', '/story/{id}', StoriesController::class . '@show', 'story.show');

// Фильтр по тегу
$router->add('GET', '/t/{tagslug}', StoriesController::class . '@index', 'tags.filter');

// Фильтр по домену
$router->add('GET', '/domain/{domain}', StoriesController::class . '@index', 'domain.show');

// Истории участника
$router->add('GET', '/user/{username}/stories', StoriesController::class . '@userStories', 'user.stories');

// =========================================================================
// МАРШРУТЫ ДЛЯ АВТОРИЗОВАННЫХ ПОЛЬЗОВАТЕЛЕЙ
// =========================================================================

$router->group(['middleware' => ['web', 'auth']], function ($router) {

    // --- Создание и редактирование историй ---
    $router->add('GET', '/stories/create', StoriesController::class . '@showCreateForm', 'story.form');
    $router->add('POST', '/stories/create', StoriesController::class . '@create', 'story.create');
	
	$router->add('POST', '/stories/fetch-url-title', StoriesController::class . '@fetchUrlTitle', 'story.fetch_url_title');

    // Предпросмотр Markdown
    $router->add('POST', '/stories/preview', StoriesController::class . '@preview', 'story.preview');

    $router->add('GET', '/stories/{id}/edit', StoriesController::class . '@showEditForm', 'story.edit');
    $router->add('POST', '/stories/{id}/edit', StoriesController::class . '@update', 'story.edit.submit');

    // --- Подписки и прочтение ---
    $router->add('POST', '/story/{id}/follow', StoriesController::class . '@toggleFollow', 'story.toggle.follow');
    $router->add('POST', '/story/{id}/mark-read', StoriesController::class . '@markRead', 'story.markRead');

   $router->add('GET', '/subscribed', StoriesController::class . '@subscribed', 'stories.subscribed');
 
});

// =========================================================================
// МАРШРУТЫ ДЛЯ АДМИНИСТРАТОРОВ
// =========================================================================

$router->group(['middleware' => ['web', 'admin']], function ($router) {

    // Административные действия с историями
    $router->add('POST', '/admin/stories/{id}/delete', StoriesController::class . '@adminDelete', 'admin.story.delete');
    $router->add('POST', '/admin/stories/{id}/restore', StoriesController::class . '@adminRestore', 'admin.story.restore');
});
