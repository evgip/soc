<?php

declare(strict_types=1);

return [
    'host' => \W3a\Core\Foundation\Env::get('DB_HOST', 'MySQL-8.2'),
    'port' => \W3a\Core\Foundation\Env::get('DB_PORT', '3306'),
    'dbname' => \W3a\Core\Foundation\Env::get('DB_NAME', 'soc'),
    'username' => \W3a\Core\Foundation\Env::get('DB_USER', 'root'),
    'password' => \W3a\Core\Foundation\Env::get('DB_PASS', ''),
    'charset' => 'utf8mb4',
];
