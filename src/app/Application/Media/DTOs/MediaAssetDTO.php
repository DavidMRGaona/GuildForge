<?php

declare(strict_types=1);

namespace App\Application\Media\DTOs;

use App\Domain\Media\ValueObjects\MediaFileId;
use DateTimeImmutable;

final readonly class MediaAssetDTO
{
    public function __construct(
        public MediaFileId $id,
        public string $resourceType,
        public ?string $format,
        public int $bytes,
        public ?int $width,
        public ?int $height,
        public DateTimeImmutable $uploadedAt,
    ) {}
}
