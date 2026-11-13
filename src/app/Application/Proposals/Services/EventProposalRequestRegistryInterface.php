<?php

declare(strict_types=1);

namespace App\Application\Proposals\Services;

use App\Application\Proposals\Contracts\EventProposalRequestInterface;

/**
 * The module requests of event proposals (core#581), registered by enabled modules through
 * ModuleServiceProvider::registerEventProposalRequests().
 */
interface EventProposalRequestRegistryInterface
{
    /** `<module>.<kebab-case name>` */
    public const string KEY_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*\.[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @param  class-string  $requestClass
     * @param  ?string  $module  owning module; null for the core, whose keys start with "core."
     */
    public function register(string $requestClass, ?string $module = null): void;

    /**
     * @param  array<class-string>  $requestClasses
     */
    public function registerMany(array $requestClasses, ?string $module = null): void;

    /**
     * Every registered class (unique).
     *
     * @return list<class-string>
     */
    public function all(): array;

    /**
     * The usable requests, keyed by key, ordered by order() then key. It fails closed: a class
     * that cannot be built or is not a request, a key that does not match KEY_PATTERN or does not
     * start with its module, a key already taken (the first one registered wins), fields() that
     * throws, a field that does not fit or a dependency on a field that is not an earlier one: that
     * request is left out and logged. isAvailable() is not asked here.
     *
     * @return array<string, EventProposalRequestInterface>
     */
    public function requests(): array;

    public function request(string $key): ?EventProposalRequestInterface;

    public function unregisterModule(string $module): void;

    public function clear(): void;
}
