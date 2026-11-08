<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use App\Filament\Support\PanelNavigationGroup;
use Filament\Clusters\Cluster;

/**
 * «Configuración»: one sidebar entry, in Sistema, for every settings page of the core and the
 * modules (pages join it with `protected static ?string $cluster = SettingsCluster::class`).
 * Filament prefixes the cluster slug to their paths and route names; ClusteredPageRedirects keeps
 * the old ones working.
 */
final class SettingsCluster extends Cluster
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'configuracion';

    public static function getNavigationLabel(): string
    {
        return __('filament.settings_cluster.label');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('filament.settings_cluster.label');
    }

    public static function getNavigationGroup(): string
    {
        return PanelNavigationGroup::System->getLabel();
    }

    /**
     * Without any settings page the user may open, the cluster is forbidden (not an empty page).
     */
    public static function canAccess(): bool
    {
        return self::canAccessClusteredComponents();
    }
}
