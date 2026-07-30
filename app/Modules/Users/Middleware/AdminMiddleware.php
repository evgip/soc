<?php

declare(strict_types=1);

namespace App\Modules\Users\Middleware;

use W3a\Core\Http\Middleware\RoleMiddleware;

class AdminMiddleware extends RoleMiddleware
{
    protected string $requiredRole = 'admin';
}