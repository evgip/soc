<?php

return [
    // ═══════════════════════════════════════════
    // 🔐 YANDEX SMARTCAPTCHA
    // ═══════════════════════════════════════════
    
    'default_src' => [
        'https://captcha-api.yandex.ru',
        'https://smartcaptcha.yandex.ru',
        'https://smartcaptcha.cloud.yandex.ru',
    ],

    'script_src' => [
        'https://captcha-api.yandex.ru',
        'https://smartcaptcha.yandex.ru',
        'https://www.google.com',
        'https://www.gstatic.com',
        'https://mc.yandex.ru',
        'https://yandex.ru',                
        'https://passport.yandex.ru',        
    ],
    
    'style_src' => [
        'https://smartcaptcha.yandex.ru',
        'https://fonts.googleapis.com',
    ],
    
    'font_src' => [
        'https://smartcaptcha.yandex.ru',
        'https://fonts.gstatic.com',
    ],
    
    'img_src' => [
        'https://smartcaptcha.yandex.ru',
        'https://www.google.com',
        'https://mc.yandex.ru',
        'https://avatars.yandex.net',         // ← для аватарок Яндекса
    ],
    
    'frame_src' => [
        'https://captcha-api.yandex.ru',           
        'https://smartcaptcha.yandex.ru',
        'https://www.google.com',
        'https://recaptcha.google.com',
        'https://oauth.yandex.ru',
        'https://passport.yandex.ru',
        'https://yandex.ru',                  
    ],
    
    // 🔑 РАСШИРЕННЫЙ connect-src с поддержкой WebSocket
    'connect_src' => [
        // HTTPS домены
        'https://captcha-api.yandex.ru',           
        'https://smartcaptcha.cloud.yandex.ru',
        'https://www.google.com',
        'https://passport.yandex.ru',
        'https://oauth.yandex.ru',
        'https://mc.yandex.ru',
        'https://yandex.ru',
        'https://autofill.yandex.ru',
        'https://avatars.yandex.net',
        
        // 🆕 WebSocket домены (КРИТИЧНО для OAuth Яндекса!)
        'wss://mc.yandex.ru',             
        'wss://passport.yandex.ru',
        'wss://yandex.ru',
    ],
    
    'frame_ancestors' => [
        'none' 
    ],
    
    'hsts' => [
        'enabled' => true,
        'max_age' => 31536000,
        'include_subdomains' => true,
        'preload' => true
    ]
];