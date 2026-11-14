<?php

declare(strict_types=1);

namespace Tests\Support\Modules;

use App\Filament\Clusters\SettingsCluster;
use App\Filament\Concerns\ChecksPermissions;
use Filament\Pages\Page;

/**
 * A module's own settings page in the cluster, guarded by a permission of the module. Tests put it
 * on the panel with InteractsWithModulePanel::registerOnAdminPanel().
 */
final class OwnClusterSettingsPage extends Page
{
    use ChecksPermissions;

    public const string PERMISSION = 'fixture.settings';

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $slug = 'ajustes-del-modulo-de-prueba';

    public static function canAccess(): bool
    {
        return self::userCan(self::PERMISSION);
    }
}
