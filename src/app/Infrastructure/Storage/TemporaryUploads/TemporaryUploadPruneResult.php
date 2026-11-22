<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\TemporaryUploads;

/**
 * What one pruning of the temporary uploads did (core#827). `listed` is false when the directory
 * was refused or could not be read, so nothing was looked at.
 */
final readonly class TemporaryUploadPruneResult
{
    public function __construct(
        public bool $listed,
        public int $deleted = 0,
        public int $failed = 0,
    ) {}

    public static function notListed(): self
    {
        return new self(listed: false);
    }
}
