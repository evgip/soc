<?php

$basePath = dirname(__DIR__, 2);

return [
    'app' => [
        'name' => 'w3a',
        'env' => env('APP_ENV', 'development'),
        'url' => env('APP_URL', 'http://localhost'),
        'lang' => env('APP_LANG', 'ru'),
		'theme' => 'default',
		
		// Доверенные proxy (если используете свой proxy, добавьте его IP/CIDR)
		// Если используете Cloudflare, оставьте пустым массивом — будут использоваться встроенные диапазоны
		'trusted_proxies' => [],
		
        // Путь к логам (читается CoreServiceProvider)
        'log_path' => $basePath . '/storage/logs/app.log',
    ],
    
    'database' => [
        'host' => env('DB_HOST', 'MySQL-8.2'),
        'port' => env('DB_PORT', '3306'),
        'dbname' => env('DB_NAME', 'soc'),
        'username' => env('DB_USER', 'root'),
        'password' => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],

	// Настройки кэша (читается CoreServiceProvider)
    'cache' => [
        'file' => [
            'path' => $basePath . '/storage/cache/data',
        ],
        'database' => [
            'enabled' => true,
            'ttl' => 3600,
        ],
    ],
];