<?php

use App\Modules\Collections\Controllers\CollectionsController;

// ============================================================
// 1. ТОЧНЫЕ МАРШРУТЫ (обязательно ПЕРВЫМИ!)
//    Если их поставить после /collections/{username},
//    то 'create' и 'my' будут восприняты как username.
// ============================================================
$router->group(['middleware' => ['web', 'auth']], function ($router) {
    // Форма создания — ДОЛЖНА быть ДО /collections/{username}
    $router->add('GET', '/collections/create', CollectionsController::class . '@showCreateForm', 'collections.create');
    
    // Список коллекций текущего автора (AJAX) — ДОЛЖЕН быть ДО /collections/{username}/{slug}
    $router->add('GET', '/collections/my/list', CollectionsController::class . '@myCollectionsList', 'collections.myList');
    
    // Создание коллекции
    $router->add('POST', '/collections', CollectionsController::class . '@create', 'collections.store');
    
    // CRUD с числовым {id}
    $router->add('GET', '/collections/{id}/edit', CollectionsController::class . '@showEditForm', 'collections.edit');
    $router->add('POST', '/collections/{id}', CollectionsController::class . '@update', 'collections.update');
    $router->add('POST', '/collections/{id}/delete', CollectionsController::class . '@delete', 'collections.delete');
    
    // AJAX: управление статьями в коллекции
    $router->add('POST', '/collections/{id}/stories/add', CollectionsController::class . '@addStory', 'collections.addStory');
    $router->add('POST', '/collections/{id}/stories/remove', CollectionsController::class . '@removeStory', 'collections.removeStory');
    $router->add('POST', '/collections/{id}/stories/reorder', CollectionsController::class . '@reorderStories', 'collections.reorder');
    $router->add('GET', '/collections/{id}/stories/available', CollectionsController::class . '@availableStories', 'collections.availableStories');
});

// ============================================================
// 2. ПУБЛИЧНЫЕ МАРШРУТЫ С ПАРАМЕТРАМИ (ПОСЛЕ точных)
// ============================================================

// Список коллекций пользователя
$router->add('GET', '/collections/{username}', CollectionsController::class . '@index', 'collections.index');

// Страница коллекции (оглавление)
$router->add('GET', '/collections/{username}/{slug}', CollectionsController::class . '@show', 'collections.show');