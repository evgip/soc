<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);

return [
    'file' => [
        'path' => $basePath . '/storage/cache/data',
    ],
    'database' => [
        'enabled' => true,
        'ttl' => 3600,
    ],
];
