<?php

declare(strict_types=1);

namespace App\Application\Updates\DTOs;

use Illuminate\Support\Collection;

/**
 * Outcome of checking every installed module against its GitHub repository.
 */
final readonly class UpdateCheckResultDTO
{
    /**
     * @param  Collection<int, AvailableUpdateDTO>  $updates
     * @param  array<string, string>  $errors  Module name => why its repository could not be checked
     * @param  list<string>  $modulesWithoutSource  Modules with no GitHub repository configured
     * @param  list<BlockedReleaseDTO>  $blocked  Newer releases this host cannot run
     */
    public function __construct(
        public Collection $updates,
        public array $errors,
        public array $modulesWithoutSource,
        public array $blocked = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
