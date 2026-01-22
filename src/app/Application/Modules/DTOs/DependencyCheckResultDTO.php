<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

final readonly class DependencyCheckResultDTO
{
    /**
     * @param array<string> $missing Missing module names
     * @param array<string, array{required: string, current: string}> $versionMismatch
     * @param array<array<string>> $circularDependencies List of cycles
     * @param array<string, string> $unsatisfiedRequirements Requirement key => error message
     */
    public function __construct(
        public bool $satisfied,
        public array $missing = [],
        public array $versionMismatch = [],
        public array $circularDependencies = [],
        public array $unsatisfiedRequirements = [],
    ) {
    }

    public function hasErrors(): bool
    {
        return !$this->satisfied;
    }

    /**
     * @return array<string>
     */
    public function getErrorMessages(): array
    {
        if ($this->satisfied) {
            return [];
        }

        $messages = [];

        foreach ($this->missing as $module) {
            $messages[] = "Missing required module: {$module}";
        }

        foreach ($this->versionMismatch as $module => $versions) {
            $messages[] = "Module '{$module}' requires version {$versions['required']}, but {$versions['current']} is installed";
        }

        foreach ($this->circularDependencies as $cycle) {
            $cycleStr = implode(' -> ', $cycle);
            $messages[] = "Circular dependency detected: {$cycleStr}";
        }

        foreach ($this->unsatisfiedRequirements as $requirement => $message) {
            $messages[] = $message;
        }

        return $messages;
    }
}
