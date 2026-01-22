<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

final readonly class ScaffoldResultDTO
{
    public const STATUS_CREATED = 'created';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_OVERWRITTEN = 'overwritten';

    public const STATUS_FAILED = 'failed';

    /**
     * @param array<string, string> $files Files created/modified with their status
     * @param array<string> $errors List of error messages
     * @param array<string> $warnings List of warning messages
     */
    public function __construct(
        public bool $success,
        public string $message,
        public array $files = [],
        public array $errors = [],
        public array $warnings = [],
    ) {
    }

    /**
     * Create a successful result.
     *
     * @param array<string, string> $files
     * @param array<string> $warnings
     */
    public static function success(string $message, array $files = [], array $warnings = []): self
    {
        return new self(
            success: true,
            message: $message,
            files: $files,
            warnings: $warnings,
        );
    }

    /**
     * Create a failed result.
     *
     * @param array<string> $errors
     */
    public static function failure(string $message, array $errors = []): self
    {
        return new self(
            success: false,
            message: $message,
            errors: $errors,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return ! $this->success;
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }

    /**
     * Get count of files with specific status.
     */
    public function countFilesByStatus(string $status): int
    {
        return count(array_filter($this->files, fn (string $s): bool => $s === $status));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'files' => $this->files,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
