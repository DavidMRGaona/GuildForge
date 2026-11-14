<?php

declare(strict_types=1);

namespace Tests\Support\Modules;

use App\Filament\Clusters\SettingsCluster;
use Filament\Pages\Page;

/**
 * A settings page of the cluster that no panel registers, as when a module declares a page it
 * forgot to list in registerFilamentPages().
 */
final class UnregisteredClusterSettingsPage extends Page
{
    protected static ?string $cluster = SettingsCluster::class;
}
