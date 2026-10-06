<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

use App\Domain\Modules\ValueObjects\CompatibilityIssue;

final readonly class RejectedModuleDTO
{
    /**
     * @param  list<CompatibilityIssue>  $issues
     */
    public function __construct(
        public string $name,
        public string $displayName,
        public array $issues,
    ) {
    }
}
