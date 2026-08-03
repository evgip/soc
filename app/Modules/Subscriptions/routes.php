<?php
$router->group(['middleware' => ['web', 'auth']], function ($router) {
    $router->add('POST', '/subscribe/user/{id}', \App\Modules\Subscriptions\Controllers\SubscriptionController::class . '@toggleUser', 'subscribe.user.toggle');
    $router->add('POST', '/subscribe/tag/{id}', \App\Modules\Subscriptions\Controllers\SubscriptionController::class . '@toggleTag', 'subscribe.tag.toggle');
});