<?php

declare(strict_types=1);

namespace App\Application\Seo\DTOs;

use App\Application\Seo\SeoType;

/**
 * Generic input for the metadata of one page. Core pages and modules build
 * one of these; the factory turns it into SeoMetadata.
 */
final readonly class SeoPageDTO
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $imagePublicId = null,
        public ?string $imageAlt = null,
        public SeoType $type = SeoType::Website,
        public ?SeoScheduleDTO $schedule = null,
        public ?SeoArticleDTO $article = null,
        public bool $noindex = false,
    ) {}
}
