<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Domain\Knowledge\ValueObjects\GuideConditions;

interface GuideConditionEvaluatorInterface
{
    /**
     * Whether every condition holds for the user. Fails closed: an unknown or failing
     * capability, or a disabled module, means false.
     */
    public function passes(object $user, GuideConditions $conditions, ?string $module): bool;
}
