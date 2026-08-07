<?php

return [
    'yandex' => [
        'client_id' => getenv('YANDEX_CLIENT_ID') ?: '',
        'client_secret' => getenv('YANDEX_CLIENT_SECRET') ?: '',
    ],
    'vk' => [
        'client_id' => getenv('VK_CLIENT_ID') ?: '',
        'client_secret' => getenv('VK_CLIENT_SECRET') ?: '',
    ],
];