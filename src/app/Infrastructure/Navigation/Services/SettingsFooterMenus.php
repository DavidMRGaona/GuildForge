<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Services;

use App\Application\Navigation\Services\FooterMenusInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Navigation\Enums\MenuLocation;
use JsonException;

/**
 * Names of the footer menus in the settings row `footer_menu_names`, a JSON object keyed by location.
 */
final readonly class SettingsFooterMenus implements FooterMenusInterface
{
    public const string SETTING_KEY = 'footer_menu_names';

    public function __construct(
        private SettingsServiceInterface $settings,
    ) {}

    public function label(MenuLocation $location): string
    {
        if ($location === MenuLocation::Header) {
            return __('filament.menu_items.locations.header');
        }

        return $this->names()[$location->value] ?? $this->defaultLabel($location);
    }

    public function options(): array
    {
        $names = $this->names();
        $options = [];

        foreach (MenuLocation::footers() as $location) {
            $options[$location->value] = $names[$location->value] ?? $this->defaultLabel($location);
        }

        return $options;
    }

    public function names(): array
    {
        $stored = $this->settings->get(self::SETTING_KEY);

        if (! is_string($stored) || $stored === '') {
            return [];
        }

        try {
            $decoded = json_decode($stored, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $this->clean($decoded) : [];
    }

    public function saveNames(array $names): void
    {
        $clean = $this->clean($names);

        $this->settings->set(
            self::SETTING_KEY,
            $clean === [] ? '' : json_encode($clean, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
    }

    public function defaultLabel(MenuLocation $location): string
    {
        $number = $location->footerNumber();

        return $number === 1
            ? __('filament.menu_items.locations.footer')
            : __('filament.menu_items.locations.footer_numbered', ['number' => $number]);
    }

    /**
     * @param  array<array-key, mixed>  $names
     * @return array<string, string>
     */
    private function clean(array $names): array
    {
        $clean = [];

        foreach (MenuLocation::footers() as $location) {
            $name = $names[$location->value] ?? null;
            $name = is_string($name) ? trim($name) : '';

            if ($name !== '') {
                $clean[$location->value] = mb_substr($name, 0, self::MAX_NAME_LENGTH);
            }
        }

        return $clean;
    }
}
