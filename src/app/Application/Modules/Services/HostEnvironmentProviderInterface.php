<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Domain\Modules\ValueObjects\HostEnvironment;

interface HostEnvironmentProviderInterface
{
    /**
     * Versions and extensions of the running host, computed once per process.
     */
    public function current(): HostEnvironment;
}
