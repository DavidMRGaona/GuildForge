<?php

declare(strict_types=1);

namespace App\Application\Search\Services;

use App\Application\Search\Contracts\IndexedSearchSourceInterface;

interface SearchSourceRegistryInterface
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
     * Resolved indexed sources in registration order, one per type().
     *
     * @return list<IndexedSearchSourceInterface>
     */
    public function indexedSources(): array;

    /**
     * Registered classes that may own an indexed type but could not be loaded: they failed to resolve,
     * are no indexed search source, or their type() threw. A source skipped because its type was taken
     * is not listed.
     *
     * @return list<class-string>
     */
    public function unresolved(): array;

    /**
     * Module that registered the indexed source of this type, or null for core (or an unknown type).
     */
    public function moduleOf(string $type): ?string;
}
