<?php

declare(strict_types=1);

namespace App\Application\Content\Services;

use App\Application\Content\Contracts\EditorTextSourceInterface;

/**
 * The editor text sources of the core and of the loaded modules (core#496).
 */
interface EditorTextSourceRegistryInterface
{
    /**
     * @param  class-string  $sourceClass
     */
    public function register(string $sourceClass, ?string $module = null): void;

    /**
     * @param  array<class-string>  $sourceClasses
     */
    public function registerMany(array $sourceClasses, ?string $module = null): void;

    public function unregisterModule(string $module): void;

    public function clear(): void;

    /**
     * Resolved sources in registration order, one per type().
     *
     * @return list<EditorTextSourceInterface>
     */
    public function sources(): array;

    public function source(string $type): ?EditorTextSourceInterface;

    /**
     * Registered classes left out of sources(): they failed to resolve, are no editor text source, their
     * type() threw or their type was already taken. Their texts cannot be read, so nobody may assume they
     * name no local copy.
     *
     * @return list<class-string>
     */
    public function unresolved(): array;
}
