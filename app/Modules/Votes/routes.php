<?php

use App\Modules\Votes\Controllers\VotesController;

$router->group(['middleware' => ['web', 'auth']], function($router) {

    $router->add('POST', '/clap/{id}', VotesController::class . '@clap', 'votes.clap');

    $router->add('POST', '/comment-like/{id}', VotesController::class . '@likeComment', 'votes.comment_like');
});