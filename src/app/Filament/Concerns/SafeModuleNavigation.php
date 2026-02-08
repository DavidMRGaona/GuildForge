<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Safety net for module Filament pages: if the module's route is not
 * registered (stale cache, race condition during deploy), hide the
 * navigation item instead of crashing the entire admin panel with a 500.
 */
trait SafeModuleNavigation
{
    public static function shouldRegisterNavigation(): bool
    {
        try {
            static::getUrl();
        } catch (RouteNotFoundException) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }
}
