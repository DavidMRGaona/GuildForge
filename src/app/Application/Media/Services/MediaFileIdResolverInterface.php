<?php

declare(strict_types=1);

namespace App\Application\Media\Services;

use App\Domain\Media\ValueObjects\MediaFileId;

/**
 * Turns what the application stores for a file (a database path or a delivery URL)
 * into the normalized id the media index uses.
 */
interface MediaFileIdResolverInterface
{
    /**
     * Accepts a path as stored in the database ("events/2026/11/a.jpg") or a Cloudinary
     * delivery URL of this site. Null when it is not a Cloudinary file or belongs to another prefix.
     */
    public function fromStoredPath(string $path): ?MediaFileId;

    /** Null when the public id does not start with "<prefix>/" */
    public function fromPublicId(string $prefixedPublicId): ?MediaFileId;

    /** "image", "video" or "raw" */
    public function resourceType(string $storedPath): string;

    public function prefix(): string;
}
