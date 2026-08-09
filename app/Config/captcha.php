<?php

/**
 * Конфигурация модуля Captcha
 * 
 * Все чувствительные данные (ключи API) читаются из .env файла.
 * Это позволяет:
 * - Не хранить секреты в коде
 * - Использовать разные ключи для разных окружений (dev/staging/prod)
 * - Легко менять настройки без правки кода
 */

return [
    // ═══════════════════════════════════════════
    // 🔑 ГЛОБАЛЬНЫЕ НАСТРОЙКИ
    // ═══════════════════════════════════════════
    
    // Включить/выключить капчу глобально
    'enabled' => env('CAPTCHA_ENABLED', true),
    
    // Драйвер капчи: 'yandex', 'google', 'custom'
    'driver' => env('CAPTCHA_DRIVER', 'custom'),

    // ═══════════════════════════════════════════
    // 🔐 YANDEX SMARTCAPTCHA
    // ═══════════════════════════════════════════
    // Получите ключи: https://smartcaptcha.yandex.ru/
    
    'yandex' => [
        'site_key'   => env('YANDEX_CAPTCHA_SITE_KEY', ''),
        'secret_key' => env('YANDEX_CAPTCHA_SECRET_KEY', ''),
        'submit_url' => env('YANDEX_CAPTCHA_SUBMIT_URL', 
                            'https://smartcaptcha.cloud.yandex.ru/validate'),
    ],

    // ═══════════════════════════════════════════
    // 🔐 GOOGLE RECAPTCHA V2
    // ═══════════════════════════════════════════
    // Получите ключи: https://www.google.com/recaptcha/admin
    
    'google' => [
        'site_key'   => env('GOOGLE_CAPTCHA_SITE_KEY', ''),
        'secret_key' => env('GOOGLE_CAPTCHA_SECRET_KEY', ''),
        'submit_url' => env('GOOGLE_CAPTCHA_SUBMIT_URL', 
                            'https://www.google.com/recaptcha/api/siteverify'),
    ],

    // ═══════════════════════════════════════════
    // 🎯 ДОПОЛНИТЕЛЬНЫЕ НАСТРОЙКИ
    // ═══════════════════════════════════════════
    
    // Не показывать капчу авторизованным пользователям
    'skip_for_authorized_users' => true,
    
    // Минимальная карма для пропуска капчи (если включено выше)
    'min_karma_to_skip' => 50,
];
