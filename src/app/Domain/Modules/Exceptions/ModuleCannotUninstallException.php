<?php

declare(strict_types=1);

namespace App\Domain\Modules\Exceptions;

use DomainException;

final class ModuleCannotUninstallException extends DomainException
{
    /**
     * @param  array<string>  $dependents
     */
    public static function hasDependents(string $module, array $dependents): self
    {
        $dependentsList = implode(', ', $dependents);

        return new self(__('modules.errors.cannot_uninstall_with_dependents', [
            'name' => $module,
            'dependents' => $dependentsList,
        ]));
    }

    public static function deletionFailed(string $module, string $error): self
    {
        return new self("Failed to uninstall module \"{$module}\": {$error}");
    }
}
