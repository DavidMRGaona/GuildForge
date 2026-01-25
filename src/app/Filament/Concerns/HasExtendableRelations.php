<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

/**
 * Allows Filament Resources to be extended with additional RelationManagers
 * from external modules without modifying the core Resource class.
 *
 * Usage in Resource:
 *   1. Add `use HasExtendableRelations;` to your Resource
 *   2. Move your relations from `getRelations()` to `getBaseRelations()`
 *   3. Modules can call `YourResource::extendRelations([...])` to add their RelationManagers
 *
 * @example
 * // In your Resource class:
 * class EventResource extends Resource
 * {
 *     use HasExtendableRelations;
 *
 *     protected static function getBaseRelations(): array
 *     {
 *         return []; // Your base RelationManagers
 *     }
 * }
 *
 * // In a module's ServiceProvider:
 * EventResource::extendRelations([
 *     RegistrationsRelationManager::class,
 * ]);
 */
trait HasExtendableRelations
{
    /**
     * Additional RelationManagers added by modules.
     *
     * @var array<class-string>
     */
    protected static array $extendedRelations = [];

    /**
     * Extend this Resource with additional RelationManagers.
     * Called by modules during boot to inject their RelationManagers.
     *
     * @param  array<class-string>  $relationManagers
     */
    public static function extendRelations(array $relationManagers): void
    {
        static::$extendedRelations = array_merge(
            static::$extendedRelations,
            $relationManagers
        );
    }

    /**
     * Get all RelationManagers for this Resource.
     * Combines base relations with extended relations from modules.
     * Filters out relation managers whose model relationships don't exist.
     *
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        $baseRelations = static::getBaseRelations();
        $extendedRelations = static::filterValidRelations(static::$extendedRelations);

        return array_merge($baseRelations, $extendedRelations);
    }

    /**
     * Filter extended relations to only include those whose model relationship exists.
     * This prevents errors when a module is disabled but its relation manager was cached.
     *
     * @param  array<class-string>  $relationManagers
     * @return array<class-string>
     */
    protected static function filterValidRelations(array $relationManagers): array
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = static::getModel();

        return array_filter($relationManagers, static function (string $relationManagerClass) use ($modelClass): bool {
            if (! class_exists($relationManagerClass)) {
                return false;
            }

            // Get the relationship name from the relation manager
            $relationshipName = $relationManagerClass::getRelationshipName();

            // Check if the model has this relationship:
            // 1. As a native method on the model
            // 2. As a dynamic relationship via resolveRelationUsing()
            return method_exists($modelClass, $relationshipName)
                || self::hasRelationResolver($modelClass, $relationshipName);
        });
    }

    /**
     * Check if a model has a relation resolver registered for the given relationship.
     */
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private static function hasRelationResolver(string $modelClass, string $relationshipName): bool
    {
        // Use reflection to check if the relation resolver exists
        // The relationResolvers property is protected static on the Model class
        try {
            $reflection = new \ReflectionClass($modelClass);
            $property = $reflection->getProperty('relationResolvers');
            $property->setAccessible(true);

            /** @var array<string, array<string, \Closure>> $resolvers */
            $resolvers = $property->getValue();

            return isset($resolvers[$modelClass][$relationshipName]);
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * Get the base RelationManagers for this Resource.
     * Override this in your Resource to define your own RelationManagers.
     *
     * @return array<class-string>
     */
    protected static function getBaseRelations(): array
    {
        return [];
    }

    /**
     * Clear extended relations.
     * Useful for testing to reset state between tests.
     */
    public static function clearExtendedRelations(): void
    {
        static::$extendedRelations = [];
    }
}
