<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

final readonly class CompatibilityResult
{
    /**
     * @param  list<CompatibilityIssue>  $issues
     */
    public function __construct(
        public array $issues,
    ) {
    }

    public static function compatible(): self
    {
        return new self([]);
    }

    public function isCompatible(): bool
    {
        return $this->issues === [];
    }

    public function summary(): string
    {
        return implode('; ', array_map(static fn (CompatibilityIssue $issue): string => $issue->describe(), $this->issues));
    }

    /**
     * @return list<array{requirement: string, required: string, found: string|null, reason_key: string}>
     */
    public function toArray(): array
    {
        return array_map(static fn (CompatibilityIssue $issue): array => $issue->toArray(), $this->issues);
    }
}
