<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ArticleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_article_in_database(): void
    {
        $author = UserModel::factory()->create();

        $article = ArticleModel::factory()->create([
            'title' => 'Introduction to Warhammer',
            'slug' => 'introduction-to-warhammer',
            'content' => 'Warhammer is a tabletop miniature wargame.',
            'excerpt' => 'A brief intro to Warhammer.',
            'is_published' => true,
            'author_id' => $author->id,
        ]);

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'title' => 'Introduction to Warhammer',
            'slug' => 'introduction-to-warhammer',
            'excerpt' => 'A brief intro to Warhammer.',
            'is_published' => true,
            'author_id' => $author->id,
        ]);
    }

    public function test_it_has_correct_fillable_attributes(): void
    {
        $fillable = [
            'id',
            'title',
            'slug',
            'content',
            'excerpt',
            'featured_image_public_id',
            'is_published',
            'published_at',
            'author_id',
        ];

        $model = new ArticleModel;

        $this->assertEquals($fillable, $model->getFillable());
    }

    public function test_it_casts_boolean_correctly(): void
    {
        $author = UserModel::factory()->create();

        $article = ArticleModel::factory()->create([
            'is_published' => true,
            'author_id' => $author->id,
        ]);

        $this->assertTrue($article->is_published);
        $this->assertIsBool($article->is_published);
    }

    public function test_it_casts_datetime_correctly(): void
    {
        $author = UserModel::factory()->create();

        $article = ArticleModel::factory()->published()->create([
            'published_at' => '2024-06-15 10:00:00',
            'author_id' => $author->id,
        ]);

        $this->assertInstanceOf(\DateTimeInterface::class, $article->published_at);
        $this->assertEquals('2024-06-15', $article->published_at->format('Y-m-d'));
    }

    public function test_factory_creates_draft_by_default(): void
    {
        $author = UserModel::factory()->create();

        $article = ArticleModel::factory()->create([
            'author_id' => $author->id,
        ]);

        $this->assertFalse($article->is_published);
        $this->assertNull($article->published_at);
    }

    public function test_factory_published_state_works(): void
    {
        $author = UserModel::factory()->create();

        $article = ArticleModel::factory()->published()->create([
            'author_id' => $author->id,
        ]);

        $this->assertTrue($article->is_published);
        $this->assertNotNull($article->published_at);
    }

    public function test_it_belongs_to_author(): void
    {
        $author = UserModel::factory()->create([
            'name' => 'John Doe',
        ]);

        $article = ArticleModel::factory()->create([
            'author_id' => $author->id,
        ]);

        $this->assertInstanceOf(UserModel::class, $article->author);
        $this->assertEquals('John Doe', $article->author->name);
    }
}
