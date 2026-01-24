<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Article;
use App\Domain\Exceptions\CannotPublishWithoutAuthorException;
use App\Domain\ValueObjects\ArticleId;
use App\Domain\ValueObjects\Slug;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ArticleTest extends TestCase
{
    public function test_it_creates_article_with_required_data(): void
    {
        $id = ArticleId::generate();
        $title = 'Introduction to Warhammer 40K';
        $slug = new Slug('introduction-to-warhammer-40k');
        $content = 'Warhammer 40K is a tabletop miniature wargame...';
        $authorId = 1;

        $article = new Article(
            id: $id,
            title: $title,
            slug: $slug,
            content: $content,
            authorId: $authorId,
        );

        $this->assertEquals($id, $article->id());
        $this->assertEquals($title, $article->title());
        $this->assertEquals($slug, $article->slug());
        $this->assertEquals($content, $article->content());
        $this->assertEquals($authorId, $article->authorId());
        $this->assertNull($article->excerpt());
        $this->assertNull($article->featuredImagePublicId());
        $this->assertFalse($article->isPublished());
        $this->assertNull($article->publishedAt());
    }

    public function test_it_creates_article_with_all_data(): void
    {
        $id = ArticleId::generate();
        $title = 'Painting Miniatures Guide';
        $slug = new Slug('painting-miniatures-guide');
        $content = 'A comprehensive guide to painting miniatures...';
        $excerpt = 'Learn the basics of miniature painting.';
        $featuredImagePublicId = 'articles/painting-guide.jpg';
        $isPublished = true;
        $publishedAt = new DateTimeImmutable('2024-01-15 10:00:00');
        $authorId = 1;
        $createdAt = new DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new DateTimeImmutable('2024-01-02 15:30:00');

        $article = new Article(
            id: $id,
            title: $title,
            slug: $slug,
            content: $content,
            authorId: $authorId,
            excerpt: $excerpt,
            featuredImagePublicId: $featuredImagePublicId,
            isPublished: $isPublished,
            publishedAt: $publishedAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        $this->assertEquals($id, $article->id());
        $this->assertEquals($title, $article->title());
        $this->assertEquals($slug, $article->slug());
        $this->assertEquals($content, $article->content());
        $this->assertEquals($authorId, $article->authorId());
        $this->assertEquals($excerpt, $article->excerpt());
        $this->assertEquals($featuredImagePublicId, $article->featuredImagePublicId());
        $this->assertTrue($article->isPublished());
        $this->assertEquals($publishedAt, $article->publishedAt());
        $this->assertEquals($createdAt, $article->createdAt());
        $this->assertEquals($updatedAt, $article->updatedAt());
    }

    public function test_it_is_unpublished_by_default(): void
    {
        $article = $this->createArticle();

        $this->assertFalse($article->isPublished());
        $this->assertNull($article->publishedAt());
    }

    public function test_it_publishes_article(): void
    {
        $article = $this->createArticle();

        $article->publish();

        $this->assertTrue($article->isPublished());
        $this->assertNotNull($article->publishedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $article->publishedAt());
    }

    public function test_it_unpublishes_article(): void
    {
        $article = $this->createArticle(isPublished: true);

        $article->unpublish();

        $this->assertFalse($article->isPublished());
        $this->assertNull($article->publishedAt());
    }

    public function test_it_cannot_publish_without_author(): void
    {
        $article = new Article(
            id: ArticleId::generate(),
            title: 'Test Article',
            slug: new Slug('test-article'),
            content: 'Test content.',
            authorId: null,
        );

        $this->expectException(CannotPublishWithoutAuthorException::class);

        $article->publish();
    }

    private function createArticle(
        bool $isPublished = false,
        ?DateTimeImmutable $publishedAt = null,
    ): Article {
        return new Article(
            id: ArticleId::generate(),
            title: 'Test Article',
            slug: new Slug('test-article'),
            content: 'Test article content.',
            authorId: 1,
            isPublished: $isPublished,
            publishedAt: $isPublished ? ($publishedAt ?? new DateTimeImmutable) : null,
        );
    }
}
