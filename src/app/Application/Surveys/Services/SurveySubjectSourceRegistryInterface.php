<?php

declare(strict_types=1);

namespace App\Application\Surveys\Services;

use App\Application\Surveys\Contracts\SurveySubjectSourceInterface;

interface SurveySubjectSourceRegistryInterface
{
    /**
     * Register a survey subject source class, optionally scoped to a module.
     *
     * @param  class-string  $sourceClass
     */
    public function register(string $sourceClass, ?string $module = null): void;

    /**
     * Register multiple survey subject source classes, optionally scoped to a module.
     *
     * @param  array<class-string>  $sourceClasses
     */
    public function registerMany(array $sourceClasses, ?string $module = null): void;

    /**
     * Get all registered survey subject source classes (unique class-strings).
     *
     * @return array<class-string>
     */
    public function all(): array;

    /**
     * Resolve the registered sources, keyed by source type, in registration order.
     *
     * A class that cannot be resolved, is not a SurveySubjectSourceInterface or repeats a
     * type already taken is skipped and logged: the first source registered for a type wins.
     *
     * @return array<string, SurveySubjectSourceInterface>
     */
    public function sources(): array;

    /**
     * The source offering the given type, or null when none does.
     */
    public function source(string $sourceType): ?SurveySubjectSourceInterface;

    /**
     * Unregister all survey subject source classes for a module.
     */
    public function unregisterModule(string $module): void;

    /**
     * Clear all registered survey subject source classes.
     */
    public function clear(): void;
}
