<?php

declare(strict_types=1);

namespace App\Modules\Users\Middleware;

use W3a\Core\Middleware\RoleMiddleware;

class ModeratorMiddleware extends RoleMiddleware
{
    protected string $requiredRole = 'moderator';
}