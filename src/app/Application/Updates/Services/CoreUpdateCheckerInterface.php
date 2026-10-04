<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Application\Updates\DTOs\CoreUpdateStatusDTO;
use App\Domain\Updates\Exceptions\UpdateException;

/**
 * The core is deployed continuously from a branch: an update is any commit on that
 * branch that is not deployed yet (CI failed, deploy pending or broken).
 */
interface CoreUpdateCheckerInterface
{
    /**
     * @throws UpdateException When the deployed commit is unknown or GitHub cannot be queried
     */
    public function check(): CoreUpdateStatusDTO;
}
