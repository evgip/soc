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
		
		'stories' => [
            'driver'     => 'local',
            'root'       => $basePath . '/public/uploads/stories',
            'visibility' => 'public',
			'url' => '/uploads/stories',
		],	

		'collections' => [
			'driver'     => 'local',
			'root'       => $basePath . '/public/uploads/collections',
			'visibility' => 'public',
			'url' => '/uploads/collections',
		],
	]
];
