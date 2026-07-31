<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Таблицы аутентификации
    |--------------------------------------------------------------------------
    | Ядро читает эти имена из конфига. Если переименуете таблицу в БД —
    | меняйте только здесь, код ядра трогать не нужно.
    */
    'tables' => [
        'users'             => 'users',
        'remember_tokens'   => 'remember_tokens',
        'password_resets'   => 'password_resets',
        'email_activations' => 'email_activations',
        'user_bans'         => 'user_bans',
        'banned_ips'        => 'banned_ips',
    ],

    /*
    |--------------------------------------------------------------------------
    | Маппинг колонок (Column Mapping)
    |--------------------------------------------------------------------------
    | Если переименуете колонку в БД — измените только здесь.
    */
    'columns' => [
        'users' => [
            'id'        => 'id',
            'username'  => 'username',
            'email'     => 'email',
            'password'  => 'password',
            'role'      => 'role',
            'is_active' => 'is_active',
        ],
        'remember_tokens' => [
            'id'               => 'id',
            'user_id'          => 'user_id',
            'selector'         => 'selector',
            'hashed_validator' => 'hashed_validator',
            'user_agent'       => 'user_agent',
            'ip_address'       => 'ip_address',
            'expires_at'       => 'expires_at',
            'created_at'       => 'created_at',
        ],
        'email_activations' => [
            'id'         => 'id',
            'user_id'    => 'user_id',
            'token'      => 'token',
            'created_at' => 'created_at',
        ],
        'password_resets' => [
            'id'         => 'id',
            'email'      => 'email',
            'token'      => 'token',
            'created_at' => 'created_at',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Настройки "Запомнить меня"
    |--------------------------------------------------------------------------
    */
    'remember_me' => [
        'cookie_name' => 'remember_me',
        'days'        => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Настройки восстановления пароля
    |--------------------------------------------------------------------------
    */
    'password_reset' => [
        'token_lifetime_minutes' => 60, // 1 час
    ],
];