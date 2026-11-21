<?php

declare(strict_types=1);

namespace App\Application\Activity\DTOs;

use App\Domain\Activity\ActivityKind;

/**
 * What the registry keeps of an activity source (core#774): its type and kind, its owner (null for
 * the core) and the class (or container id) to build it from. Never an instance: whoever reads the
 * source builds it in its own request or job.
 */
final readonly class ActivitySourceRegistrationDTO
{
    public function __construct(
        public string $type,
        public ActivityKind $kind,
        public ?string $module,
        public string $class,
    ) {}
}
