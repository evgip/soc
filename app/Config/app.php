<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);

return [
    'name' => 'w3a',
    'env' => \W3a\Core\Foundation\Env::get('APP_ENV', 'development'),
    'url' => \W3a\Core\Foundation\Env::get('APP_URL', 'http://localhost'),
    'lang' => \W3a\Core\Foundation\Env::get('APP_LANG', 'ru'),
    'theme' => 'default',
    
    // Доверенные proxy
    'trusted_proxies' => [],
    
    // Путь к логам
    'log_path' => $basePath . '/storage/logs/app.log',
];