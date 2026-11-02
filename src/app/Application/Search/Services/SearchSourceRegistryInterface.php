<?php

declare(strict_types=1);

namespace App\Application\Search\Services;

use App\Application\Search\Contracts\SearchSourceInterface;

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
     * Resolved sources in registration order; classes that fail to resolve are logged and left out.
     *
     * @return list<SearchSourceInterface>
     */
    public function sources(): array;
}
