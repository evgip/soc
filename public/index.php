<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// 1. Загрузка переменных окружения
\W3a\Core\Foundation\Env::load(dirname(__DIR__) . '/.env');

// 2. 🔥 ИНИЦИАЛИЗАЦИЯ СЕССИИ (Вернули, так как это критично для CSRF и авторизации)
if (session_status() === PHP_SESSION_NONE) {
    $isProduction = \W3a\Core\Foundation\Env::get('APP_ENV', 'development') === 'production';
    
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
               || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
               || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $useSecure = $isProduction && $isHttps;

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '', 
        'secure' => $useSecure, 
        'httponly' => true,
        'samesite' => 'Lax', 
    ]);

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    $sessionName = \W3a\Core\Foundation\Env::get('SESSION_NAME', 'w3a_session');
    session_name($sessionName);
    
    session_start();
}

// 3. Запуск приложения
$app = new \W3a\Core\Foundation\Application(dirname(__DIR__), [
    \W3a\Core\Foundation\CoreServiceProvider::class,
    \App\AppServiceProvider::class,
]);

$app->bootstrap()->run();