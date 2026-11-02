<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Search\Sources;

use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SiteSearchQueryDTO;
use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Domain\Search\Enums\SearchAudience;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Search\Search\DatabaseSiteSearch;
use App\Infrastructure\Search\Sources\ArticleSearchSource;
use App\Infrastructure\Search\Sources\EventSearchSource;
use App\Infrastructure\Search\Sources\GallerySearchSource;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CoreSearchSourcesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unpublished_event_has_no_document(): void
    {
        $event = EventModel::factory()->create(['is_published' => false]);

        $this->assertNull($this->app->make(EventSearchSource::class)->document($event->id));
        $this->assertNull($this->app->make(EventSearchSource::class)->document((string) Str::uuid()));
    }

    /**
     * @param  class-string<IndexedSearchSourceInterface>  $source
     */
    #[DataProvider('coreSources')]
    public function test_non_uuid_id_has_no_document(string $source): void
    {
        $this->assertNull($this->app->make($source)->document('not-a-uuid'));
    }

    /**
     * @return array<string, array{class-string<IndexedSearchSourceInterface>}>
     */
    public static function coreSources(): array
    {
        return [
            'events' => [EventSearchSource::class],
            'articles' => [ArticleSearchSource::class],
            'galleries' => [GallerySearchSource::class],
        ];
    }

    public function test_event_document_carries_dates_tags_and_location(): void
    {
        $event = EventModel::factory()->create([
            'title' => 'Torneo de primavera',
            'slug' => 'torneo-de-primavera',
            'description' => '<p>Una jornada de <strong>wargames</strong></p>',
            'location' => 'Sala principal',
            'image_public_id' => 'events/torneo',
            'start_date' => now()->addDays(3),
            'end_date' => now()->addDays(4),
            'is_published' => true,
        ]);
        $event->tags()->attach([
            TagModel::factory()->create(['name' => 'Warhammer'])->id,
            TagModel::factory()->create(['name' => 'Torneo'])->id,
        ]);

        $source = $this->app->make(EventSearchSource::class);
        $document = $source->document($event->id);

        $this->assertNotNull($document);
        $this->assertSame($event->id, $document->id);
        $this->assertSame('Torneo de primavera', $document->title);
        $this->assertSame('Una jornada de wargames', $document->body);
        $this->assertSame(route('events.show', 'torneo-de-primavera'), $document->url);
        $this->assertSame('Sala principal', $document->meta);
        $this->assertSame('events/torneo', $document->imagePublicId);
        $this->assertEqualsCanonicalizing(['Warhammer', 'Torneo'], explode(' ', (string) $document->keywords));
        $this->assertSame($event->start_date->getTimestamp(), $document->startsAt?->getTimestamp());
        $this->assertSame($event->end_date->getTimestamp(), $document->endsAt?->getTimestamp());
        $this->assertFalse($document->finished);
        $this->assertSame(SearchAudience::Public, $document->audience);

        $this->assertSame('events', $source->type());
        $this->assertSame('Eventos', $source->label());
        $this->assertSame('Evento', $source->itemLabel());
        $this->assertSame('Celebrado', $source->pastLabel());
    }

    public function test_past_event_is_past_by_its_dates_not_by_finished(): void
    {
        $event = EventModel::factory()->create([
            'title' => 'Liga de invierno',
            'start_date' => now()->subDays(3),
            'end_date' => now()->subDays(2),
            'is_published' => true,
        ]);

        $document = $this->app->make(EventSearchSource::class)->document($event->id);

        // Dates make a result past when searching; finished is only for records over by status
        $this->assertNotNull($document);
        $this->assertFalse($document->finished);

        $this->app->make(SearchDocumentStoreInterface::class)->upsert('events', null, $document);
        $hits = $this->app->make(DatabaseSiteSearch::class)->search(new SiteSearchQueryDTO(
            query: 'invierno',
            audiences: [SearchAudience::Public],
            types: ['events'],
            type: null,
            offset: 0,
            limit: 20,
            user: null,
            includeHelp: false,
        ))->hits;

        $this->assertCount(1, $hits);
        $this->assertTrue($hits[0]->past);
    }

    public function test_article_body_has_no_html(): void
    {
        $article = ArticleModel::factory()->create([
            'title' => 'Crónica',
            'slug' => 'cronica',
            'excerpt' => 'Resumen &amp; avance',
            'content' => '<h2>Titular</h2><p>Texto del <em>artículo</em></p>',
            'featured_image_public_id' => 'articles/cronica',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);
        $article->tags()->attach(TagModel::factory()->create(['name' => 'Crónicas'])->id);

        $source = $this->app->make(ArticleSearchSource::class);
        $document = $source->document($article->id);

        $this->assertNotNull($document);
        $this->assertSame('Resumen & avance Titular Texto del artículo', $document->body);
        $this->assertSame('Crónicas', $document->keywords);
        $this->assertSame(route('articles.show', 'cronica'), $document->url);
        $this->assertSame('articles/cronica', $document->imagePublicId);
        $this->assertSame($article->published_at?->getTimestamp(), $document->publishedAt?->getTimestamp());
        $this->assertSame('articles', $source->type());
        $this->assertSame('Artículos', $source->label());
        $this->assertSame('Artículo', $source->itemLabel());
        $this->assertNull($source->pastLabel());
    }

    public function test_gallery_body_includes_photo_captions(): void
    {
        $gallery = GalleryModel::factory()->create([
            'title' => 'Jornada',
            'slug' => 'jornada',
            'description' => '<p>Fotos del día</p>',
            'is_published' => true,
        ]);
        PhotoModel::factory()->create(['gallery_id' => $gallery->id, 'caption' => 'Mesa de juego', 'image_public_id' => 'galleries/first', 'sort_order' => 1]);
        PhotoModel::factory()->create(['gallery_id' => $gallery->id, 'caption' => '', 'image_public_id' => 'galleries/second', 'sort_order' => 2]);
        PhotoModel::factory()->create(['gallery_id' => $gallery->id, 'caption' => 'Miniaturas pintadas', 'image_public_id' => 'galleries/third', 'sort_order' => 3]);

        $source = $this->app->make(GallerySearchSource::class);
        $document = $source->document($gallery->id);

        $this->assertNotNull($document);
        $this->assertSame("Fotos del día\nMesa de juego\nMiniaturas pintadas", $document->body);
        $this->assertSame('galleries/first', $document->imagePublicId);
        $this->assertSame(route('galleries.show', 'jornada'), $document->url);
        $this->assertSame('galleries', $source->type());
        $this->assertSame('Galerías', $source->label());
        $this->assertSame('Galería', $source->itemLabel());
        $this->assertNull($source->pastLabel());
    }

    public function test_documents_lists_only_published_content(): void
    {
        $event = EventModel::factory()->create(['is_published' => true]);
        EventModel::factory()->create(['is_published' => false]);
        $article = ArticleModel::factory()->create(['is_published' => true, 'published_at' => now()]);
        ArticleModel::factory()->create(['is_published' => false]);
        $gallery = GalleryModel::factory()->create(['is_published' => true]);
        GalleryModel::factory()->create(['is_published' => false]);

        $ids = static fn (iterable $documents): array => array_map(static fn ($document): string => $document->id, [...$documents]);

        $this->assertSame([$event->id], $ids($this->app->make(EventSearchSource::class)->documents()));
        $this->assertSame([$article->id], $ids($this->app->make(ArticleSearchSource::class)->documents()));
        $this->assertSame([$gallery->id], $ids($this->app->make(GallerySearchSource::class)->documents()));
    }
}
