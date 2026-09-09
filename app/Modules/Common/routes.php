<?php

/**
 * Маршруты модуля Wiki (вложенные в теги)
 *
 * ВАЖНО: Порядок маршрутов имеет значение!
 * Более специфичные маршруты должны идти первыми.
 *
 * @var W3a\Core\Http\Router $router
 */

use App\Modules\Common\Controllers\CommonController;

/**
 * Просмотр wiki страницы тега
 */
$router->add(
    'GET',
    '/support/donations',
    CommonController::class . '@index',
    'donations'
);
