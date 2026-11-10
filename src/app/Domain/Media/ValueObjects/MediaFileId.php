<?php

declare(strict_types=1);

namespace App\Domain\Media\ValueObjects;

use App\Domain\Media\Exceptions\InvalidMediaFileIdException;

/**
 * Public id of a stored file, without prefix or extension (e.g. events/2026/11/<uuid>).
 */
final readonly class MediaFileId
{
    public function __construct(public string $value)
    {
        if ($value === '' || str_starts_with($value, '/') || str_ends_with($value, '/') || str_contains($value, '..')) {
            throw InvalidMediaFileIdException::invalid($value);
        }
    }

    /** Directory of the file with a trailing slash; empty when the file sits at the root */
    public function folder(): string
    {
        $position = strrpos($this->value, '/');

        return $position === false ? '' : substr($this->value, 0, $position + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
