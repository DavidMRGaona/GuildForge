<?php

declare(strict_types=1);

namespace Tests\Support\Modules;

use App\Filament\Clusters\SettingsCluster;
use Filament\Pages\Page;
use RuntimeException;

/**
 * A settings page of the cluster whose access check fails, as a broken module's might.
 */
final class ThrowingAccessClusterSettingsPage extends Page
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $slug = 'ajustes-que-fallan';

    public static function canAccess(): bool
    {
        throw new RuntimeException('The access check failed.');
    }
}
