<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Entities\Article;
use App\Domain\Repositories\ArticleRepositoryInterface;
use App\Domain\ValueObjects\ArticleId;
use App\Domain\ValueObjects\Slug;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentArticleRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentArticleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentArticleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentArticleRepository;
    }

    public function test_it_implements_article_repository_interface(): void
    {
        $this->assertInstanceOf(ArticleRepositoryInterface::class, $this->repository);
    }

    public function test_it_finds_by_id(): void
    {
        $author = UserModel::factory()->create();
        $model = ArticleModel::factory()->create([
            'title' => 'Test Article',
            'slug' => 'test-article',
            'author_id' => $author->id,
        ]);

        $article = $this->repository->findById(new ArticleId($model->id));

        $this->assertNotNull($article);
        $this->assertEquals($model->id, $article->id()->value);
        $this->assertEquals('Test Article', $article->title());
        $this->assertEquals('test-article', $article->slug()->value);
    }

    public function test_it_returns_null_when_not_found(): void
    {
        $article = $this->repository->findById(ArticleId::generate());

        $this->assertNull($article);
    }

    public function test_it_finds_by_slug(): void
    {
        $author = UserModel::factory()->create();
        $model = ArticleModel::factory()->create([
            'title' => 'Warhammer Guide',
            'slug' => 'warhammer-guide',
            'author_id' => $author->id,
        ]);

        $article = $this->repository->findBySlug('warhammer-guide');

        $this->assertNotNull($article);
        $this->assertEquals($model->id, $article->id()->value);
        $this->assertEquals('Warhammer Guide', $article->title());
    }

    public function test_it_returns_null_when_slug_not_found(): void
    {
        $article = $this->repository->findBySlug('non-existent-slug');

        $this->assertNull($article);
    }

    public function test_it_finds_published_articles(): void
    {
        $author = UserModel::factory()->create();
        ArticleModel::factory()->published()->count(4)->create(['author_id' => $author->id]);
        ArticleModel::factory()->draft()->count(3)->create(['author_id' => $author->id]);

        $articles = $this->repository->findPublished();

        $this->assertCount(4, $articles);
        $articles->each(function (Article $article) {
            $this->assertTrue($article->isPublished());
        });
    }

    public function test_it_orders_published_articles_by_published_at_desc(): void
    {
        $author = UserModel::factory()->create();

        ArticleModel::factory()->published()->create([
            'title' => 'First Article',
            'published_at' => now()->subDays(10),
            'author_id' => $author->id,
        ]);
        ArticleModel::factory()->published()->create([
            'title' => 'Latest Article',
            'published_at' => now()->subDays(1),
            'author_id' => $author->id,
        ]);
        ArticleModel::factory()->published()->create([
            'title' => 'Middle Article',
            'published_at' => now()->subDays(5),
            'author_id' => $author->id,
        ]);

        $articles = $this->repository->findPublished();

        $this->assertEquals('Latest Article', $articles->first()->title());
        $this->assertEquals('First Article', $articles->last()->title());
    }

    public function test_it_finds_articles_by_author(): void
    {
        $author1 = UserModel::factory()->create();
        $author2 = UserModel::factory()->create();

        ArticleModel::factory()->count(3)->create(['author_id' => $author1->id]);
        ArticleModel::factory()->count(2)->create(['author_id' => $author2->id]);

        $articles = $this->repository->findByAuthor($author1->id);

        $this->assertCount(3, $articles);
        $articles->each(function (Article $article) use ($author1) {
            $this->assertEquals($author1->id, $article->authorId());
        });
    }

    public function test_it_saves_new_article(): void
    {
        $author = UserModel::factory()->create();
        $id = ArticleId::generate();

        $article = new Article(
            id: $id,
            title: 'New Article',
            slug: new Slug('new-article'),
            content: 'This is a new article.',
            authorId: $author->id,
            excerpt: 'A brief excerpt.',
            featuredImagePublicId: 'articles/new-article.jpg',
            isPublished: true,
            publishedAt: new DateTimeImmutable('2024-06-15 10:00:00'),
        );

        $this->repository->save($article);

        $this->assertDatabaseHas('articles', [
            'id' => $id->value,
            'title' => 'New Article',
            'slug' => 'new-article',
            'content' => 'This is a new article.',
            'excerpt' => 'A brief excerpt.',
            'featured_image_public_id' => 'articles/new-article.jpg',
            'is_published' => true,
            'author_id' => $author->id,
        ]);
    }

    public function test_it_updates_existing_article(): void
    {
        $author = UserModel::factory()->create();
        $model = ArticleModel::factory()->create([
            'title' => 'Original Title',
            'slug' => 'original-slug',
            'is_published' => false,
            'author_id' => $author->id,
        ]);

        $article = new Article(
            id: new ArticleId($model->id),
            title: 'Updated Title',
            slug: new Slug('updated-slug'),
            content: 'Updated content.',
            authorId: $author->id,
            excerpt: 'Updated excerpt.',
            isPublished: true,
            publishedAt: new DateTimeImmutable('2024-06-20 14:00:00'),
        );

        $this->repository->save($article);

        $this->assertDatabaseHas('articles', [
            'id' => $model->id,
            'title' => 'Updated Title',
            'slug' => 'updated-slug',
            'content' => 'Updated content.',
            'excerpt' => 'Updated excerpt.',
            'is_published' => true,
        ]);

        $this->assertDatabaseMissing('articles', [
            'title' => 'Original Title',
        ]);
    }

    public function test_it_deletes_article(): void
    {
        $author = UserModel::factory()->create();
        $model = ArticleModel::factory()->create(['author_id' => $author->id]);
        $article = $this->repository->findById(new ArticleId($model->id));

        $this->assertNotNull($article);

        $this->repository->delete($article);

        $this->assertDatabaseMissing('articles', [
            'id' => $model->id,
        ]);
    }
}
