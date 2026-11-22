<?php

declare(strict_types=1);

namespace App\Domain\Modules\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The module settings saved on the site could not be read, so whatever needed them (a queued
 * job) must not run with values that may be outdated or with the release defaults in their place.
 */
final class ModuleSettingsUnavailableException extends RuntimeException
{
    public static function forModule(string $moduleName, Throwable $previous): self
    {
        return new self("The stored settings of module {$moduleName} could not be read.", 0, $previous);
    }

    public static function versionUnreadable(Throwable $previous): self
    {
        return new self('The settings version could not be read, so the module settings may be outdated.', 0, $previous);
    }
}
