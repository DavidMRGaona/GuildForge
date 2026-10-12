<?php

declare(strict_types=1);

namespace App\Application\Seo\DTOs;

use DateTimeInterface;

final readonly class SeoArticleDTO
{
    public function __construct(
        public DateTimeInterface $publishedAt,
        public ?DateTimeInterface $modifiedAt = null,
        public ?string $authorName = null,
    ) {}
}
