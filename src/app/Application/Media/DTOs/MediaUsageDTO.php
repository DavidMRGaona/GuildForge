<?php

declare(strict_types=1);

namespace App\Application\Media\DTOs;

/**
 * One use of a stored file by a record.
 *
 * `storedPath` is the path as stored in the database (with extension) or the full Cloudinary URL;
 * `role` is the translated name of what the file is for («Imagen», «Logo claro»…). A source sets `hidden`
 * when the image itself must stay out of sight (the badge of a secret achievement): the media library then
 * shows a placeholder and no link to the file. It never changes whether the file is used or can be deleted.
 */
final readonly class MediaUsageDTO
{
    public function __construct(
        public string $storedPath,
        public string $recordId,
        public string $role,
        public string $title,
        public bool $hidden = false,
    ) {}
}
