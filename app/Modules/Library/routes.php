<?php

use App\Modules\Library\Controllers\LibraryController;

$router->group(['middleware' => ['web', 'auth']], function ($router) {
    $router->add('GET', '/me/library', LibraryController::class . '@index', 'library.index');
});