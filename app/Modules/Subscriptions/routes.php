<?php

use App\Modules\Subscriptions\Controllers\SubscriptionController;

$router->group(['middleware' => ['web', 'auth']], function ($router) {
    $router->add('POST', '/subscribe/user/{id}', SubscriptionController::class . '@toggleUser', 'subscribe.user.toggle');
    $router->add('POST', '/subscribe/tag/{id}', SubscriptionController::class . '@toggleTag', 'subscribe.tag.toggle');
	$router->add('POST', '/subscribe/collection/{id}', SubscriptionController::class . '@toggleCollection', 'subscribe.collection.toggle');
});