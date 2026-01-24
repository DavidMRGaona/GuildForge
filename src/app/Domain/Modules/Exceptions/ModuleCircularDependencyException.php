<?php

declare(strict_types=1);

namespace App\Domain\Modules\Exceptions;

use DomainException;

final class ModuleCircularDependencyException extends DomainException
{
    /**
     * @param  array<string>  $cycle
     */
    public static function detected(array $cycle): self
    {
        $cycleStr = implode(' -> ', $cycle).' -> '.$cycle[0];

        return new self("Circular dependency detected: {$cycleStr}");
    }
}
