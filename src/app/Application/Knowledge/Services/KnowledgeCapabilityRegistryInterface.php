<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;

interface KnowledgeCapabilityRegistryInterface
{
    /**
     * @param  class-string<KnowledgeCapabilityInterface>  $capabilityClass
     */
    public function register(string $capabilityClass, ?string $module = null): void;

    /**
     * @param  array<class-string<KnowledgeCapabilityInterface>>  $capabilityClasses
     */
    public function registerMany(array $capabilityClasses, ?string $module = null): void;

    public function unregisterModule(string $module): void;

    public function clear(): void;

    /**
     * Keys of the capabilities that resolve, in registration order. The first class
     * registered for a key wins; duplicates are logged and skipped.
     *
     * @return list<string>
     */
    public function keys(): array;

    /**
     * The capability result, or null when the key is unknown, the class does not
     * resolve or the capability throws (logged).
     */
    public function evaluate(string $key, object $user): ?bool;
}
