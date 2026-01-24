<?php

declare(strict_types=1);

namespace App\Application\DTOs;

final readonly class CreateArticleDTO
{
    public function __construct(
        public string $title,
        public string $content,
        public int $authorId,
        public ?string $excerpt = null,
        public ?string $featuredImagePublicId = null,
    ) {}

    /**
     * @param  array{title: string, content: string, author_id: int, excerpt?: string|null, featured_image_public_id?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            content: $data['content'],
            authorId: $data['author_id'],
            excerpt: $data['excerpt'] ?? null,
            featuredImagePublicId: $data['featured_image_public_id'] ?? null,
        );
    }
}
