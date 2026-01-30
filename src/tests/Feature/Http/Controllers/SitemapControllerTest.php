<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class SitemapControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sitemap_returns_xml_response(): void
    {
        // Act
        $response = $this->get('/sitemap.xml');

        // Assert
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
    }

    public function test_sitemap_includes_static_urls(): void
    {
        // Act
        $response = $this->get('/sitemap.xml');

        // Assert
        $response->assertOk();
        $response->assertSee('<loc>'.url('/').'</loc>', false);
        $response->assertSee('<loc>'.url('/nosotros').'</loc>', false);
        $response->assertSee('<loc>'.url('/eventos').'</loc>', false);
        $response->assertSee('<loc>'.url('/articulos').'</loc>', false);
        $response->assertSee('<loc>'.url('/galeria').'</loc>', false);
    }

    public function test_sitemap_includes_published_events(): void
    {
        // Arrange
        $publishedEvent1 = EventModel::factory()->published()->create([
            'slug' => 'warhammer-tournament',
        ]);
        $publishedEvent2 = EventModel::factory()->published()->create([
            'slug' => 'dnd-session',
        ]);

        // Act
        $response = $this->get('/sitemap.xml');

        // Assert
        $response->assertOk();
        $response->assertSee('<loc>'.url('/eventos/warhammer-tournament').'</loc>', false);
        $response->assertSee('<loc>'.url('/eventos/dnd-session').'</loc>', false);
    }

    public function test_sitemap_excludes_unpublished_events(): void
    {
        // Arrange
        $draftEvent = EventModel::factory()->draft()->create([
            'slug' => 'draft-event',
        ]);

        // Act
        $response = $this->get('/sitemap.xml');

        // Assert
        $response->assertOk();
        $response->assertDontSee('<loc>'.url('/eventos/draft-event').'</loc>', false);
    }

    public function test_sitemap_includes_published_articles(): void
    {
        // Arrange
        $publishedArticle1 = ArticleModel::factory()->published()->create([
            'slug' => 'painting-guide',
        ]);
        $publishedArticle2 = ArticleModel::factory()->published()->create([
            'slug' => 'game-review',
        ]);

        // Act
        $response = $this->get('/sitemap.xml');

        // Assert
        $response->assertOk();
        $response->assertSee('<loc>'.url('/articulos/painting-guide').'</loc>', false);
        $response->assertSee('<loc>'.url('/articulos/game-review').'</loc>', false);
    }

    public function test_sitemap_excludes_unpublished_articles(): void
    {
        // Arrange
        $draftArticle = ArticleModel::factory()->draft()->create([
            'slug' => 'draft-article',
        ]);

        // Act
        $response = $this->get('/sitemap.xml');

        // Assert
        $response->assertOk();
        $response->assertDontSee('<loc>'.url('/articulos/draft-article').'</loc>', false);
    }

    public function test_sitemap_includes_published_galleries(): void
    {
        // Arrange
        $publishedGallery1 = GalleryModel::factory()->published()->create([
            'slug' => 'tournament-2024',
        ]);
        $publishedGallery2 = GalleryModel::factory()->published()->create([
            'slug' => 'painted-miniatures',
        ]);

        // Act
        $response = $this->get('/sitemap.xml');

        // Assert
        $response->assertOk();
        $response->assertSee('<loc>'.url('/galeria/tournament-2024').'</loc>', false);
        $response->assertSee('<loc>'.url('/galeria/painted-miniatures').'</loc>', false);
    }

    public function test_sitemap_excludes_unpublished_galleries(): void
    {
        // Arrange
        $draftGallery = GalleryModel::factory()->draft()->create([
            'slug' => 'draft-gallery',
        ]);

        // Act
        $response = $this->get('/sitemap.xml');

        // Assert
        $response->assertOk();
        $response->assertDontSee('<loc>'.url('/galeria/draft-gallery').'</loc>', false);
    }

    public function test_sitemap_has_valid_xml_structure(): void
    {
        // Act
        $response = $this->get('/sitemap.xml');

        // Assert
        $response->assertOk();
        $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false);
        $response->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false);
        $response->assertSee('</urlset>', false);
    }

    public function test_sitemap_includes_lastmod_dates(): void
    {
        // Arrange
        $event = EventModel::factory()->published()->create([
            'slug' => 'test-event',
        ]);
        $article = ArticleModel::factory()->published()->create([
            'slug' => 'test-article',
        ]);
        $gallery = GalleryModel::factory()->published()->create([
            'slug' => 'test-gallery',
        ]);

        // Act
        $response = $this->get('/sitemap.xml');

        // Assert
        $response->assertOk();
        $response->assertSee('<lastmod>'.$event->updated_at->toAtomString().'</lastmod>', false);
        $response->assertSee('<lastmod>'.$article->updated_at->toAtomString().'</lastmod>', false);
        $response->assertSee('<lastmod>'.$gallery->updated_at->toAtomString().'</lastmod>', false);
    }

    public function test_sitemap_includes_changefreq_and_priority(): void
    {
        // Arrange
        EventModel::factory()->published()->create([
            'slug' => 'test-event',
        ]);

        // Act
        $response = $this->get('/sitemap.xml');

        // Assert
        $response->assertOk();
        $response->assertSee('<changefreq>', false);
        $response->assertSee('<priority>', false);
    }
}
