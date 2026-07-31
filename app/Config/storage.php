<?php
declare(strict_types=1);

$basePath = dirname(__DIR__, 2);

return [
    'disks' => [
        'avatars' => [
            'driver' => 'local',
            'root' => $basePath . '/public/uploads/avatars',
            'visibility' => 'public',
            'url' => '/uploads/avatars',
        ],
    ],
];
