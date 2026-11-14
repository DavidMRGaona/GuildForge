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
 * ToggleButtons) and switches (Toggle, Checkbox), named without dots. An allowlist of exact
 * classes, so neither a field Filament adds later nor a subclass (which could store anything) is
 * accepted until someone looks at it; a field that cannot be read is refused.
 */
final class FlatSettingsSchemaRule
{
    /** @var list<class-string<Field>> Exact classes: a subclass is not flat */
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
        return $this->names($components) !== null;
    }

    /**
     * The names of the fields, in order, when every one is flat; null otherwise. They are the only
     * settings a module block gets back.
     *
     * @param  array<mixed>  $components
     * @return list<string>|null
     */
    public function names(array $components): ?array
    {
        $names = [];

        foreach ($components as $component) {
            $name = $this->flatName($component);

            if ($name === null) {
                return null;
            }

            $names[] = $name;
        }

        return $names;
    }

    private function flatName(mixed $component): ?string
    {
        if (! is_object($component) || ! in_array($component::class, self::FLAT_FIELDS, true) || ! $component instanceof Field) {
            return null;
        }

        try {
            $name = $component->getName();

            if ($name === '' || str_contains($name, '.')) {
                return null;
            }

            if (($component instanceof Select || $component instanceof ToggleButtons) && $component->isMultiple()) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return $name;
    }
}
