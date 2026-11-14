<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteBlocks;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Throwable;

/**
 * Whether the settings of a module site block are flat (core#696): every field one value of its
 * own, with nothing that stores files or editor HTML. The footer and the popups keep those values
 * in their own stored JSON, where no module could register an upload or an editor image as its
 * own (media library, editor image move), so the first version only allows fields that keep a
 * plain value: short text, numbers and links (TextInput), single choices (Select, Radio,
 * ToggleButtons) and switches (Toggle, Checkbox), named without dots. An allowlist, so a field
 * Filament adds later is refused until someone looks at it; a field that cannot be read is refused.
 */
final class FlatSettingsSchemaRule
{
    /** @var list<class-string<Field>> */
    private const array FLAT_FIELDS = [
        TextInput::class,
        Select::class,
        Radio::class,
        ToggleButtons::class,
        Toggle::class,
        Checkbox::class,
    ];

    /**
     * @param  array<mixed>  $components
     */
    public function allows(array $components): bool
    {
        return array_all($components, fn ($component) => $this->isFlat($component));
    }

    private function isFlat(mixed $component): bool
    {
        if (! $component instanceof Field || ! $this->isAllowedField($component)) {
            return false;
        }

        try {
            if (str_contains($component->getName(), '.')) {
                return false;
            }

            if (($component instanceof Select || $component instanceof ToggleButtons) && $component->isMultiple()) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    private function isAllowedField(Field $component): bool
    {
        return array_any(self::FLAT_FIELDS, fn ($class) => $component instanceof $class);
    }
}
