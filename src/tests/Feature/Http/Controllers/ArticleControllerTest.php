<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ArticleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_published_articles(): void
    {
        ArticleModel::factory()->published()->count(3)->create();
        ArticleModel::factory()->draft()->count(2)->create();

        $response = $this->get('/articulos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Index')
                ->has('articles.data', 3)
        );
    }

    public function test_index_paginates_articles(): void
    {
        ArticleModel::factory()->published()->count(15)->create();

        $response = $this->get('/articulos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Index')
                ->has('articles.data', 12)
                ->has('articles.meta.currentPage')
                ->has('articles.meta.lastPage')
                ->has('articles.meta.perPage')
                ->has('articles.meta.total')
        );
    }

    public function test_index_orders_articles_by_published_date_descending(): void
    {
        $oldArticle = ArticleModel::factory()->published()->create([
            'title' => 'Old Article',
            'published_at' => now()->subDays(10),
        ]);
        $newArticle = ArticleModel::factory()->published()->create([
            'title' => 'New Article',
            'published_at' => now()->subDays(1),
        ]);

        $response = $this->get('/articulos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Index')
                ->has('articles.data', 2)
                ->where('articles.data.0.title', 'New Article')
                ->where('articles.data.1.title', 'Old Article')
        );
    }

    public function test_index_includes_author_data(): void
    {
        $author = UserModel::factory()->create([
            'name' => 'Test Author',
            'display_name' => 'Test Display Name',
        ]);

        ArticleModel::factory()
            ->published()
            ->withAuthor($author)
            ->create();

        $response = $this->get('/articulos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Index')
                ->has('articles.data.0.author')
                ->has('articles.data.0.author.id')
                ->has('articles.data.0.author.name')
                ->has('articles.data.0.author.displayName')
        );
    }

    public function test_show_displays_single_published_article(): void
    {
        $author = UserModel::factory()->create();

        $article = ArticleModel::factory()->published()->withAuthor($author)->create([
            'title' => 'Test Article',
            'slug' => 'test-article',
            'content' => 'Test content',
            'excerpt' => 'Test excerpt',
        ]);

        $response = $this->get('/articulos/test-article');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Show')
                ->has('article')
                ->where('article.title', 'Test Article')
                ->where('article.slug', 'test-article')
                ->where('article.content', 'Test content')
                ->where('article.excerpt', 'Test excerpt')
        );
    }

    public function test_show_includes_author_data(): void
    {
        $author = UserModel::factory()->create([
            'name' => 'John Doe',
            'display_name' => 'John',
            'avatar_public_id' => 'avatar.jpg',
        ]);

        $article = ArticleModel::factory()
            ->published()
            ->withAuthor($author)
            ->create([
                'slug' => 'test-article',
            ]);

        $response = $this->get('/articulos/test-article');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Show')
                ->has('article.author')
                ->has('article.author.id')
                ->where('article.author.name', 'John Doe')
                ->where('article.author.displayName', 'John')
                ->where('article.author.avatarPublicId', 'avatar.jpg')
        );
    }

    public function test_show_returns_404_for_unpublished_article(): void
    {
        ArticleModel::factory()->draft()->create([
            'slug' => 'unpublished-article',
        ]);

        $response = $this->get('/articulos/unpublished-article');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_nonexistent_article(): void
    {
        $response = $this->get('/articulos/nonexistent-article');

        $response->assertStatus(404);
    }
}
