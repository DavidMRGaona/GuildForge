<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ArticleControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

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

    public function test_index_includes_tags_in_response(): void
    {
        $tag1 = TagModel::factory()->forArticles()->create([
            'name' => 'Tutorial',
            'slug' => 'tutorial',
        ]);
        $tag2 = TagModel::factory()->forArticles()->create([
            'name' => 'Battle Report',
            'slug' => 'battle-report',
        ]);

        TagModel::factory()->forEvents()->create();

        $response = $this->get('/articulos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Index')
                ->has('tags', 2)
                ->where('tags.0.name', fn ($val) => in_array($val, ['Tutorial', 'Battle Report']))
                ->where('tags.1.name', fn ($val) => in_array($val, ['Tutorial', 'Battle Report']))
        );
    }

    public function test_index_includes_current_tag_filter(): void
    {
        TagModel::factory()->forArticles()->create([
            'slug' => 'tutorial',
        ]);

        $response = $this->get('/articulos?tags=tutorial');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Index')
                ->where('currentTags', ['tutorial'])
        );
    }

    public function test_index_filters_articles_by_tag(): void
    {
        $tag = TagModel::factory()->forArticles()->create([
            'name' => 'Tutorial',
            'slug' => 'tutorial',
        ]);

        $author = UserModel::factory()->create();

        $taggedArticle = ArticleModel::factory()->published()->withAuthor($author)->create([
            'title' => 'How to Paint Miniatures',
        ]);
        $taggedArticle->tags()->attach($tag->id);

        $untaggedArticle = ArticleModel::factory()->published()->withAuthor($author)->create([
            'title' => 'Game Review',
        ]);

        $response = $this->get('/articulos?tags=tutorial');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Index')
                ->has('articles.data', 1)
                ->where('articles.data.0.title', 'How to Paint Miniatures')
        );
    }

    public function test_index_shows_all_articles_when_no_tag_filter(): void
    {
        $tag = TagModel::factory()->forArticles()->create();
        $author = UserModel::factory()->create();

        $taggedArticle = ArticleModel::factory()->published()->withAuthor($author)->create(['title' => 'Tagged Article']);
        $taggedArticle->tags()->attach($tag->id);

        $untaggedArticle = ArticleModel::factory()->published()->withAuthor($author)->create(['title' => 'Untagged Article']);

        $response = $this->get('/articulos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Index')
                ->has('articles.data', 2)
        );
    }

    public function test_index_returns_empty_when_tag_has_no_articles(): void
    {
        $tag = TagModel::factory()->forArticles()->create([
            'slug' => 'unused-tag',
        ]);

        $author = UserModel::factory()->create();
        ArticleModel::factory()->published()->withAuthor($author)->create();

        $response = $this->get('/articulos?tags=unused-tag');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Index')
                ->has('articles.data', 0)
        );
    }

    public function test_show_includes_article_tags(): void
    {
        $tag1 = TagModel::factory()->forArticles()->create([
            'name' => 'Tutorial',
            'slug' => 'tutorial',
        ]);
        $tag2 = TagModel::factory()->forArticles()->create([
            'name' => 'Painting',
            'slug' => 'painting',
        ]);

        $author = UserModel::factory()->create();
        $article = ArticleModel::factory()->published()->withAuthor($author)->create([
            'slug' => 'test-article',
        ]);
        $article->tags()->attach([$tag1->id, $tag2->id]);

        $response = $this->get('/articulos/test-article');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Show')
                ->has('article.tags', 2)
                ->where('article.tags.0.name', fn ($val) => in_array($val, ['Tutorial', 'Painting']))
                ->where('article.tags.1.name', fn ($val) => in_array($val, ['Tutorial', 'Painting']))
        );
    }

    public function test_show_displays_article_without_tags(): void
    {
        $author = UserModel::factory()->create();
        $article = ArticleModel::factory()->published()->withAuthor($author)->create([
            'slug' => 'untagged-article',
        ]);

        $response = $this->get('/articulos/untagged-article');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Show')
                ->has('article.tags', 0)
        );
    }

    public function test_index_filters_by_child_tag(): void
    {
        $parent = TagModel::factory()->forArticles()->create([
            'name' => 'Hobby',
            'slug' => 'hobby',
        ]);

        $child = TagModel::factory()->forArticles()->withParent($parent)->create([
            'name' => 'Painting',
            'slug' => 'painting',
        ]);

        $author = UserModel::factory()->create();
        $article = ArticleModel::factory()->published()->withAuthor($author)->create([
            'title' => 'Advanced Painting Techniques',
        ]);
        $article->tags()->attach($child->id);

        $response = $this->get('/articulos?tags=painting');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Index')
                ->has('articles.data', 1)
                ->where('articles.data.0.title', 'Advanced Painting Techniques')
        );
    }
}
