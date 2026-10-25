<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

final readonly class ScaffoldResultDTO
{
    public const string STATUS_CREATED = 'created';

    public const string STATUS_SKIPPED = 'skipped';

    public const string STATUS_OVERWRITTEN = 'overwritten';

    public const string STATUS_FAILED = 'failed';

    /**
     * @param  array<string, string>  $files  Files created/modified with their status
     * @param  array<string>  $errors  List of error messages
     * @param  array<string>  $warnings  List of warning messages
     * @param  array<string>  $nextSteps  What is left to do by hand (registering, binding, building...)
     */
    public function __construct(
        public bool $success,
        public string $message,
        public array $files = [],
        public array $errors = [],
        public array $warnings = [],
        public array $nextSteps = [],
    ) {}

    /**
     * Create a successful result.
     *
     * @param  array<string, string>  $files
     * @param  array<string>  $warnings
     * @param  array<string>  $nextSteps
     */
    public static function success(string $message, array $files = [], array $warnings = [], array $nextSteps = []): self
    {
        return new self(
            success: true,
            message: $message,
            files: $files,
            warnings: $warnings,
            nextSteps: $nextSteps,
        );
    }

    /**
     * Create a failed result.
     *
     * @param  array<string>  $errors
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
            'next_steps' => $this->nextSteps,
        ];
    }
}
