<?php

declare(strict_types=1);

namespace App\Domain\Modules\Exceptions;

use DomainException;

final class ModuleMigrationViolationException extends DomainException
{
    /**
     * @param  array<int, string>  $violations
     */
    public static function withViolations(string $moduleName, string $fileName, array $violations): self
    {
        return new self(
            moduleName: $moduleName,
            fileName: $fileName,
            violations: $violations,
        );
    }

    /**
     * @param  array<int, string>  $violations
     */
    private function __construct(
        public readonly string $moduleName,
        public readonly string $fileName,
        public readonly array $violations,
    ) {
        parent::__construct(
            "Module '{$moduleName}' migration '{$fileName}' contains prohibited operations: ".implode('; ', $violations)
        );
    }
}
