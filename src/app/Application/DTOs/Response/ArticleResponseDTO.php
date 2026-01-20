<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

use DateTimeImmutable;

final readonly class ArticleResponseDTO
{
    /**
     * @param array<TagResponseDTO> $tags
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public string $content,
        public ?string $excerpt,
        public ?string $featuredImage,
        public bool $isPublished,
        public ?DateTimeImmutable $publishedAt,
        public ?AuthorResponseDTO $author,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public array $tags = [],
    ) {
    }
}
