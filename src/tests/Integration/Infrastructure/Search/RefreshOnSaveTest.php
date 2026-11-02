<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Search;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The test queue is "sync", so the after-commit refresh job runs inside the test.
 */
final class RefreshOnSaveTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function stored(string $type, string $id): ?SearchDocumentModel
    {
        return SearchDocumentModel::query()->where('type', $type)->where('source_id', $id)->first();
    }

    public function test_publishing_an_event_creates_its_document(): void
    {
        $event = EventModel::factory()->create(['is_published' => true, 'title' => 'Liga de otoño']);

        $this->assertSame('Liga de otoño', $this->stored('events', $event->id)?->title);
    }

    public function test_unpublishing_an_event_removes_its_document(): void
    {
        $event = EventModel::factory()->create(['is_published' => true]);
        $this->assertNotNull($this->stored('events', $event->id));

        $event->update(['is_published' => false]);

        $this->assertNull($this->stored('events', $event->id));
    }

    public function test_editing_a_photo_caption_refreshes_its_gallery(): void
    {
        $gallery = GalleryModel::factory()->create(['is_published' => true, 'description' => 'Fotos']);
        $photo = PhotoModel::factory()->create(['gallery_id' => $gallery->id, 'caption' => 'Antes']);
        $this->assertStringContainsString('Antes', (string) $this->stored('galleries', $gallery->id)?->body);

        $photo->update(['caption' => 'Después']);

        $body = (string) $this->stored('galleries', $gallery->id)?->body;
        $this->assertStringContainsString('Después', $body);
        $this->assertStringNotContainsString('Antes', $body);

        $photo->delete();

        $this->assertStringNotContainsString('Después', (string) $this->stored('galleries', $gallery->id)?->body);
    }

    public function test_deleting_an_article_forgets_its_document(): void
    {
        $article = ArticleModel::factory()->create(['is_published' => true, 'published_at' => now()]);
        $this->assertNotNull($this->stored('articles', $article->id));

        $article->delete();

        $this->assertNull($this->stored('articles', $article->id));
    }

    public function test_deleting_a_gallery_forgets_its_document(): void
    {
        $gallery = GalleryModel::factory()->create(['is_published' => true]);
        PhotoModel::factory()->create(['gallery_id' => $gallery->id]);
        $this->assertNotNull($this->stored('galleries', $gallery->id));

        $gallery->delete();

        $this->assertNull($this->stored('galleries', $gallery->id));
    }
}
