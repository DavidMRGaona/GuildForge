<?php

declare(strict_types=1);

namespace App\Application\Registration\Services;

use App\Application\Registration\DTOs\RegistrationFieldDTO;

/**
 * The fields modules add to the registration form (core#699), registered by enabled modules
 * through ModuleServiceProvider::registerRegistrationFields().
 */
interface RegistrationFieldRegistryInterface
{
    /** `<module>.<kebab-case name>` */
    public const string KEY_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*\.[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @param  class-string  $fieldClass
     * @param  ?string  $module  owning module; null for the core, whose keys start with "core."
     */
    public function register(string $fieldClass, ?string $module = null): void;

    /**
     * @param  array<class-string>  $fieldClasses
     */
    public function registerMany(array $fieldClasses, ?string $module = null): void;

    /**
     * Every registered class (unique).
     *
     * @return list<class-string>
     */
    public function all(): array;

    /**
     * The fields offered right now, ordered by order() then key, each read once. It fails closed:
     * a class that cannot be built or is not a registration field, a key that does not match
     * KEY_PATTERN or does not start with its module, a key already taken (the first one registered
     * wins), an empty label, rules that are not a list, isAvailable() false, or any method that
     * throws: that field is left out (logged, except when it is simply unavailable). Never throws.
     *
     * @return list<RegistrationFieldDTO>
     */
    public function available(): array;

    public function unregisterModule(string $module): void;

    public function clear(): void;
}
