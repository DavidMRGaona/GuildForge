<?php

declare(strict_types=1);

namespace App\Domain\Modules\Exceptions;

use DomainException;

final class ModuleDependencyException extends DomainException
{
    public static function missingDependency(string $module, string $dependency): self
    {
        return new self("Cannot enable module \"{$module}\": missing dependencies");
    }

    public static function versionMismatch(string $module, string $dependency, string $required, string $current): self
    {
        return new self(
            "Module '{$module}' requires '{$dependency}' version {$required}, but version {$current} is installed."
        );
    }

    /**
     * @param  array<string>  $dependents
     */
    public static function dependentModulesExist(string $module, array $dependents): self
    {
        $dependentsList = implode(', ', $dependents);

        return new self("Cannot disable module \"{$module}\". It is required by: {$dependentsList}");
    }
}
