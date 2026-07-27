<?php

declare(strict_types=1);

namespace App\Modules\Origins;

use W3a\Core\Container;
use App\Modules\Origins\Models\Domain;

/**
 * Провайдер сервисов модуля Origins.
 * 
 * Регистрирует модели модуля.
 * Сервисов в модуле нет — только модели.
 */
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        // === МОДЕЛИ ===
        
        $container->singleton(Domain::class, fn() => new Domain());
    }
}