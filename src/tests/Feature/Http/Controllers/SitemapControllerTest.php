<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Services\TimezoneServiceInterface;
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
        EventModel::factory()->published()->create([
            'slug' => 'warhammer-tournament',
        ]);
        EventModel::factory()->published()->create([
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
        EventModel::factory()->draft()->create([
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
        ArticleModel::factory()->published()->create([
            'slug' => 'painting-guide',
        ]);
        ArticleModel::factory()->published()->create([
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
        ArticleModel::factory()->draft()->create([
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
        GalleryModel::factory()->published()->create([
            'slug' => 'tournament-2024',
        ]);
        GalleryModel::factory()->published()->create([
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
        GalleryModel::factory()->draft()->create([
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
        $response->assertSee('<lastmod>'.app(TimezoneServiceInterface::class)->iso($event->updated_at).'</lastmod>', false);
        $response->assertSee('<lastmod>'.app(TimezoneServiceInterface::class)->iso($article->updated_at).'</lastmod>', false);
        $response->assertSee('<lastmod>'.app(TimezoneServiceInterface::class)->iso($gallery->updated_at).'</lastmod>', false);
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
