<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\CreateArticleDTO;
use PHPUnit\Framework\TestCase;

final class CreateArticleDTOTest extends TestCase
{
    public function test_it_creates_dto_with_required_data(): void
    {
        $title = 'Introduction to Warhammer';
        $content = 'Warhammer is a tabletop miniature wargame...';
        $authorId = 1;

        $dto = new CreateArticleDTO(
            title: $title,
            content: $content,
            authorId: $authorId,
        );

        $this->assertEquals($title, $dto->title);
        $this->assertEquals($content, $dto->content);
        $this->assertEquals($authorId, $dto->authorId);
        $this->assertNull($dto->excerpt);
        $this->assertNull($dto->featuredImagePublicId);
    }

    public function test_it_creates_dto_with_all_data(): void
    {
        $title = 'Painting Miniatures Guide';
        $content = 'A comprehensive guide to painting miniatures...';
        $authorId = 2;
        $excerpt = 'Learn the basics of miniature painting.';
        $featuredImagePublicId = 'articles/painting-guide.jpg';

        $dto = new CreateArticleDTO(
            title: $title,
            content: $content,
            authorId: $authorId,
            excerpt: $excerpt,
            featuredImagePublicId: $featuredImagePublicId,
        );

        $this->assertEquals($title, $dto->title);
        $this->assertEquals($content, $dto->content);
        $this->assertEquals($authorId, $dto->authorId);
        $this->assertEquals($excerpt, $dto->excerpt);
        $this->assertEquals($featuredImagePublicId, $dto->featuredImagePublicId);
    }

    public function test_it_creates_dto_from_array(): void
    {
        $data = [
            'title' => 'Board Games Review',
            'content' => 'This month we review the latest board games.',
            'author_id' => 3,
            'excerpt' => 'Monthly board games review.',
            'featured_image_public_id' => 'articles/board-games.jpg',
        ];

        $dto = CreateArticleDTO::fromArray($data);

        $this->assertEquals('Board Games Review', $dto->title);
        $this->assertEquals('This month we review the latest board games.', $dto->content);
        $this->assertEquals(3, $dto->authorId);
        $this->assertEquals('Monthly board games review.', $dto->excerpt);
        $this->assertEquals('articles/board-games.jpg', $dto->featuredImagePublicId);
    }

    public function test_it_creates_dto_from_array_with_only_required_fields(): void
    {
        $data = [
            'title' => 'Minimal Article',
            'content' => 'Article with minimal data.',
            'author_id' => 1,
        ];

        $dto = CreateArticleDTO::fromArray($data);

        $this->assertEquals('Minimal Article', $dto->title);
        $this->assertEquals('Article with minimal data.', $dto->content);
        $this->assertEquals(1, $dto->authorId);
        $this->assertNull($dto->excerpt);
        $this->assertNull($dto->featuredImagePublicId);
    }
}
