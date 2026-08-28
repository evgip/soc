<?php

use App\Modules\AuthorStats\Controllers\AuthorStatsController;

$router->group(['middleware' => ['web', 'auth']], function ($router) {
    $router->add('GET', '/user/stats', AuthorStatsController::class . '@index', 'user.stats');
});