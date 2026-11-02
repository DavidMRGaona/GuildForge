<?php

declare(strict_types=1);

namespace App\Filament\Support;

/**
 * How the panel sidebar collapses on desktop, chosen in the site settings.
 * Whether it is collapsed right now is each browser's choice (Filament keeps it in localStorage).
 */
enum PanelSidebarMode: string
{
    case Fixed = 'fixed';
    case Icons = 'icons';
    case Hidden = 'hidden';

    public const string SETTING_KEY = 'panel_sidebar_mode';

    public const self DEFAULT = self::Icons;

    /**
     * A missing or unknown stored value falls back to the default.
     */
    public static function fromSetting(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::DEFAULT) : self::DEFAULT;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $mode) {
            $options[$mode->value] = __('filament.settings.panel.sidebar_mode_'.$mode->value);
        }

        return $options;
    }

    public function collapses(): bool
    {
        return $this !== self::Fixed;
    }
}
