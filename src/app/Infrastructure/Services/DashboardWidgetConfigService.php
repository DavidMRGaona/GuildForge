<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Services\DashboardWidgetConfigServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use JsonException;

final class DashboardWidgetConfigService implements DashboardWidgetConfigServiceInterface
{
    private const string SETTINGS_KEY = 'dashboard_widgets';

    /**
     * In-memory cache for the decoded config to avoid repeated JSON decoding.
     *
     * @var array<string, array{enabled: bool, sort: int, limit?: int}>|null
     */
    private ?array $configCache = null;

    public function __construct(
        private readonly SettingsServiceInterface $settingsService,
    ) {}

    public function getConfig(): array
    {
        if ($this->configCache !== null) {
            return $this->configCache;
        }

        $raw = $this->settingsService->get(self::SETTINGS_KEY);

        if ($raw === null || $raw === '') {
            $this->configCache = [];

            return [];
        }

        try {
            /** @var array<string, array{enabled: bool, sort: int, limit?: int}> $decoded */
            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
            $this->configCache = $decoded;
        } catch (JsonException) {
            $this->configCache = [];
        }

        return $this->configCache;
    }

    public function isEnabled(string $widgetClass): bool
    {
        $config = $this->getConfig();

        if (! isset($config[$widgetClass])) {
            return true; // Default: enabled
        }

        return $config[$widgetClass]['enabled'];
    }

    public function getSort(string $widgetClass, int $default): int
    {
        $config = $this->getConfig();

        if (! isset($config[$widgetClass]['sort'])) {
            return $default;
        }

        return $config[$widgetClass]['sort'];
    }

    public function getLimit(string $widgetClass, int $default): int
    {
        $config = $this->getConfig();

        if (! isset($config[$widgetClass]['limit'])) {
            return $default;
        }

        return $config[$widgetClass]['limit'];
    }

    public function saveConfig(array $config): void
    {
        try {
            $json = json_encode($config, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }

        $this->settingsService->set(self::SETTINGS_KEY, $json);
        $this->configCache = null;
    }

    public function getDefaults(): array
    {
        $defaults = [];

        $widgets = Filament::getWidgets();

        foreach ($widgets as $widget) {
            if ($widget instanceof WidgetConfiguration) {
                $widgetClass = $widget->widget;
            } elseif (is_string($widget) && is_subclass_of($widget, Widget::class)) {
                $widgetClass = $widget;
            } else {
                continue;
            }

            $defaults[$widgetClass] = [
                'enabled' => true,
                'sort' => $widgetClass::getSort(),
            ];
        }

        return $defaults;
    }

    public function clearConfig(): void
    {
        $this->settingsService->set(self::SETTINGS_KEY, '');
        $this->configCache = null;
    }
}
