<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Forms\Components\Component;

/**
 * Allows Filament Resources to be extended with additional form sections
 * from external modules without modifying the core Resource class.
 *
 * Usage in Resource:
 *   1. Add `use HasExtendableFormSections;` to your Resource
 *   2. Call `static::getExtendedFormSections()` in your form schema
 *   3. Modules can call `YourResource::extendFormSections([...])` to add sections
 *
 * @example
 * // In your Resource class:
 * public static function form(Form $form): Form
 * {
 *     return $form->schema([
 *         // ... your base form fields
 *         ...static::getExtendedFormSections(),
 *     ]);
 * }
 *
 * // In a module's ServiceProvider:
 * EventResource::extendFormSections([
 *     Section::make('Registration Settings')
 *         ->schema([...])
 *         ->collapsed(),
 * ]);
 */
trait HasExtendableFormSections
{
    /**
     * Additional form sections added by modules.
     *
     * @var array<Component>
     */
    protected static array $extendedFormSections = [];

    /**
     * Extend this Resource with additional form sections.
     * Called by modules during boot to inject their form sections.
     *
     * @param  array<Component>  $sections
     */
    public static function extendFormSections(array $sections): void
    {
        static::$extendedFormSections = array_merge(
            static::$extendedFormSections,
            $sections
        );
    }

    /**
     * Get all extended form sections for this Resource.
     *
     * @return array<Component>
     */
    public static function getExtendedFormSections(): array
    {
        return static::$extendedFormSections;
    }

    /**
     * Clear extended form sections.
     * Useful for testing to reset state between tests.
     */
    public static function clearExtendedFormSections(): void
    {
        static::$extendedFormSections = [];
    }
}
