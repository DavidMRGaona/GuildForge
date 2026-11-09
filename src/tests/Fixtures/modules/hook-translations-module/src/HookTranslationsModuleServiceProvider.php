<?php

declare(strict_types=1);

namespace Modules\HookTranslationsModule;

use App\Application\Modules\DTOs\PermissionDTO;
use App\Modules\ModuleServiceProvider;

/**
 * Translates a label inside registerPermissions(), as most modules do. While the application
 * boots, Laravel boots a provider registered from another provider's boot() only after that
 * one finishes, so ModuleLoader reads these hooks before the module's own boot() has loaded
 * its translations. loadTranslations() does nothing here to reproduce that order in a test,
 * where the application is already booted.
 */
final class HookTranslationsModuleServiceProvider extends ModuleServiceProvider
{
    public static ?string $permissionLabel = null;

    public function moduleName(): string
    {
        return 'hook-translations-module';
    }

    protected function loadTranslations(): void
    {
        // Left to ModuleLoader, as during a real boot (see the class docblock)
    }

    public function registerPermissions(): array
    {
        self::$permissionLabel = __('hook-translations-module::messages.permission');

        return [
            new PermissionDTO(
                name: 'things.view',
                label: self::$permissionLabel,
                group: 'Cosas',
            ),
        ];
    }
}
