<?php

declare(strict_types=1);

namespace Modules\PaneledModule;

use App\Modules\ModuleServiceProvider;
use Modules\PaneledModule\Filament\Pages\ThingSettingsPage;

final class PaneledModuleServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'paneled-module';
    }

    public function registerFilamentPages(): array
    {
        return [ThingSettingsPage::class];
    }
}
