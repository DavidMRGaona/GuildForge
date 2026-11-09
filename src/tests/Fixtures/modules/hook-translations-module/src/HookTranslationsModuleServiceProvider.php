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
    /** Where the label read inside registerPermissions() is left for the test to check */
    public const string LABEL_CONFIG_KEY = 'testing.hook_translations_module.permission_label';

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
        $label = __('hook-translations-module::messages.permission');
        config([self::LABEL_CONFIG_KEY => $label]);

        return [
            new PermissionDTO(
                name: 'things.view',
                label: $label,
                group: 'Cosas',
            ),
        ];
    }
}
