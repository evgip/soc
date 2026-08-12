<?php

use App\Modules\Comments\Controllers\CommentsController;

// Публичные маршруты
$router->add('GET', '/comments', CommentsController::class . '@index', 'comments.index');

$router->add('GET', '/user/{username}/comments', CommentsController::class . '@userComments', 'user.comments');

// Маршруты для авторизованных пользователей
$router->group(['middleware' => ['web', 'auth']], function ($router) {
    $router->add('POST', '/comments/create', CommentsController::class . '@create', 'comment.create');
    $router->add('POST', '/comments/{id}/edit', CommentsController::class . '@edit', 'comment.edit');
    $router->add('POST', '/comments/{id}/delete', CommentsController::class . '@delete', 'comment.delete');
    $router->add('POST', '/comments/{id}/restore', CommentsController::class . '@restore', 'comment.restore');
	$router->add('POST', '/comments/create-highlight', CommentsController::class . '@createWithHighlight', 'comment.createHighlight');
});

$router->add('GET', '/comments/highlights/{storyId}', CommentsController::class . '@highlights', 'comments.highlights');