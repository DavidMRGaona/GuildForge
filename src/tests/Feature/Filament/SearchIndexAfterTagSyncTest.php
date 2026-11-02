<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Resources\GalleryResource\Pages\EditGallery;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The panel saves tags after the record, so the document built when the record was saved lacks them.
 */
final class SearchIndexAfterTagSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    private function keywords(string $type, string $id): ?string
    {
        return SearchDocumentModel::query()->where('type', $type)->where('source_id', $id)->first()?->keywords;
    }

    public function test_tags_saved_from_the_event_page_reach_the_index(): void
    {
        // Explicit dates: the factory may end an event when it starts, which the form rejects
        $event = EventModel::factory()->create([
            'is_published' => true,
            'start_date' => now()->addWeek()->setTime(18, 0),
            'end_date' => now()->addWeek()->setTime(22, 0),
        ]);
        $category = TagModel::factory()->create(['name' => 'Categoría']);
        $tag = TagModel::factory()->create(['name' => 'Escaramuza', 'parent_id' => $category->id]);
        $this->assertNull($this->keywords('events', $event->id));

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['category_id' => $category->id, 'additional_tag_ids' => [$tag->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertStringContainsString('Escaramuza', (string) $this->keywords('events', $event->id));
    }

    public function test_tags_saved_from_the_article_page_reach_the_index(): void
    {
        $article = ArticleModel::factory()->create(['is_published' => true, 'published_at' => now()]);
        $category = TagModel::factory()->create(['name' => 'Categoría']);
        $tag = TagModel::factory()->create(['name' => 'Crónicas', 'parent_id' => $category->id]);

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->fillForm(['category_id' => $category->id, 'additional_tag_ids' => [$tag->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertStringContainsString('Crónicas', (string) $this->keywords('articles', $article->id));
    }

    public function test_tags_saved_from_the_gallery_page_reach_the_index(): void
    {
        $gallery = GalleryModel::factory()->create(['is_published' => true]);
        $category = TagModel::factory()->create(['name' => 'Categoría']);
        $tag = TagModel::factory()->create(['name' => 'Pintura', 'parent_id' => $category->id]);

        Livewire::test(EditGallery::class, ['record' => $gallery->getRouteKey()])
            ->fillForm(['category_id' => $category->id, 'additional_tag_ids' => [$tag->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertStringContainsString('Pintura', (string) $this->keywords('galleries', $gallery->id));
    }
}
