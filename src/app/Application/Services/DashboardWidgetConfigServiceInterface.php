<?php

declare(strict_types=1);

namespace App\Application\Services;

interface DashboardWidgetConfigServiceInterface
{
    /**
     * Get the full widget configuration.
     *
     * @return array<string, array{enabled: bool, sort: int, limit?: int}>
     */
    public function getConfig(): array;

    /**
     * Check if a widget class is enabled.
     */
    public function isEnabled(string $widgetClass): bool;

    /**
     * Get the configured sort order for a widget.
     */
    public function getSort(string $widgetClass, int $default): int;

    /**
     * Get the configured row limit for a table widget.
     */
    public function getLimit(string $widgetClass, int $default): int;

    /**
     * Save widget configuration.
     *
     * @param  array<string, array{enabled: bool, sort: int, limit?: int}>  $config
     */
    public function saveConfig(array $config): void;

    /**
     * Get default configuration derived from registered widget classes.
     *
     * @return array<string, array{enabled: bool, sort: int}>
     */
    public function getDefaults(): array;

    /**
     * Clear saved configuration, reverting to defaults.
     */
    public function clearConfig(): void;
}
