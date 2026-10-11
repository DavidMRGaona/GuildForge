<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\ArticleResource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Users may have no display name: the article list and its author filter must fall back to the name.
 */
final class ArticleAuthorFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_article_list_renders_when_an_author_has_no_display_name(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $author = UserModel::factory()->create(['name' => 'Sin alias', 'display_name' => null]);
        ArticleModel::factory()->create(['author_id' => $author->id]);

        $this->get(ArticleResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Sin alias');
    }
}
