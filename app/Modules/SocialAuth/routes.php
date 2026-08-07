<?php
/**
 * Маршруты модуля SocialAuth
 * 
 * Социальная авторизация — публичный функционал, доступен всем (включая гостей).
 * Middleware не требуются: GET-запросы не нуждаются в CSRF-защите.
 * 
 * @var \W3a\Core\Http\Router $router
 */

use App\Modules\SocialAuth\Controllers\SocialAuthController;

// =========================================================================
// ЯНДЕКС
// =========================================================================

/**
 * Редирект на страницу авторизации Яндекса.
 * 
 * @example /auth/yandex → редирект на oauth.yandex.ru
 */
$router->add(
    'GET',
    '/auth/yandex',
    SocialAuthController::class . '@yandexRedirect',
    'social_auth.yandex'
);

/**
 * Обработка ответа от Яндекса (callback).
 * Обменивает код на токен, создаёт/логинит пользователя.
 * 
 * @example /auth/yandex/callback?code=...&state=...
 */
$router->add(
    'GET',
    '/auth/yandex/callback',
    SocialAuthController::class . '@yandexCallback',
    'social_auth.yandex.callback'
);

// =========================================================================
// ВКОНТАКТЕ
// =========================================================================

/**
 * Редирект на страницу авторизации VK.
 * 
 * @example /auth/vk → редирект на oauth.vk.com
 */
$router->add(
    'GET',
    '/auth/vk',
    SocialAuthController::class . '@vkRedirect',
    'social_auth.vk'
);

/**
 * Обработка ответа от VK (callback).
 * Обменивает код на токен, создаёт/логинит пользователя.
 * 
 * @example /auth/vk/callback?code=...&state=...
 */
$router->add(
    'GET',
    '/auth/vk/callback',
    SocialAuthController::class . '@vkCallback',
    'social_auth.vk.callback'
);