<?php

declare(strict_types=1);

namespace App\Application\Activity\DTOs;

use App\Domain\Activity\ActivityKind;

/**
 * An activity source as a consumer lists it (core#774): type, kind, translated label and owner
 * (null for the core).
 */
final readonly class ActivitySourceSummaryDTO
{
    public function __construct(
        public string $type,
        public ActivityKind $kind,
        public string $label,
        public ?string $module,
    ) {}
}
