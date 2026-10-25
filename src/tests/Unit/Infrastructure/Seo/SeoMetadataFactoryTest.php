<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Seo;

use App\Application\DTOs\Response\ArticleResponseDTO;
use App\Application\DTOs\Response\AuthorResponseDTO;
use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\DTOs\Response\GalleryDetailResponseDTO;
use App\Application\DTOs\Response\PhotoResponseDTO;
use App\Application\Seo\DTOs\SeoMetadata;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class SeoMetadataFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
        config([
            'app.name' => 'Sitio de ejemplo',
            'app.description' => 'Asociación de juegos',
            'app.timezone' => 'Europe/Madrid',
            'cloudinary.cloud_name' => 'demo',
            'cloudinary.prefix' => 'guildforge',
        ]);
        Storage::fake('images');
        $this->visit('https://example.com/eventos/club?utm_source=whatsapp');
        $this->branding(logoPath: null);
    }

    private function visit(string $url): void
    {
        $request = Request::create($url);
        $this->app->instance('request', $request);
        app('url')->setRequest($request);
    }

    private function branding(?string $logoPath, ?string $logoUrl = null, bool $logoIsDark = false): void
    {
        $branding = Mockery::mock(SiteBrandingServiceInterface::class);
        $branding->shouldReceive('siteName')->andReturn('Sitio de ejemplo');
        $branding->shouldReceive('customLogoPath')->andReturn($logoPath);
        $branding->shouldReceive('customLogoUrl')->andReturn($logoUrl);
        $branding->shouldReceive('customLogoIsDark')->andReturn($logoIsDark);
        $branding->shouldReceive('socialProfileUrls')->andReturn(['https://instagram.com/asociacion-ejemplo']);
        $this->app->instance(SiteBrandingServiceInterface::class, $branding);
    }

    private function factory(): SeoMetadataFactoryInterface
    {
        return app(SeoMetadataFactoryInterface::class);
    }

    private function event(?string $imagePublicId): EventResponseDTO
    {
        return new EventResponseDTO(
            id: 'e1',
            title: 'Club de lectura',
            slug: 'club',
            description: '<p>Traed vuestro libro</p>',
            startDate: new DateTimeImmutable('2026-01-15T17:00:00Z'),
            endDate: new DateTimeImmutable('2026-01-15T21:00:00Z'),
            location: 'La Taberna',
            memberPrice: 3.0,
            nonMemberPrice: 5.0,
            imagePublicId: $imagePublicId,
            isPublished: true,
            createdAt: null,
            updatedAt: null,
        );
    }

    public function test_event_with_image(): void
    {
        $seo = $this->factory()->forEvent($this->event('events/2026/01/club.jpg'));

        $this->assertSame(SeoMetadata::SCOPE_PAGE, $seo->scope);
        $this->assertSame('Club de lectura', $seo->title);
        $this->assertSame('Club de lectura - Sitio de ejemplo', $seo->fullTitle);
        $this->assertSame('https://example.com/eventos/club', $seo->canonicalUrl);
        $this->assertSame('15 de enero de 2026, 18:00 · La Taberna · Traed vuestro libro', $seo->description);
        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/e_trim/w_1200,h_630,c_fill,g_auto,q_auto,f_jpg/guildforge/events/2026/01/club',
            $seo->image?->url,
        );
        $this->assertTrue($seo->image->isLarge);
        $this->assertSame('Event', $seo->structuredData[0]['@type']);
        $this->assertSame('5.00', $seo->structuredData[0]['offers']['price']);
    }

    public function test_event_without_image_uses_the_custom_logo_never_the_default(): void
    {
        $this->branding(logoPath: 'settings/logo.png');

        $seo = $this->factory()->forEvent($this->event(null));

        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/w_600,h_600,c_pad,b_white,q_auto,f_png/guildforge/settings/logo',
            $seo->image?->url,
        );
        $this->assertFalse($seo->image->isLarge);
    }

    public function test_custom_logo_without_cloud_name_uses_its_storage_url_never_the_default(): void
    {
        config(['cloudinary.cloud_name' => '']);
        $this->branding(logoPath: 'settings/logo.png', logoUrl: '/storage/settings/logo.png');

        $seo = $this->factory()->forEvent($this->event('events/club.jpg'));

        $this->assertSame('https://example.com/storage/settings/logo.png', $seo->image?->url);
        $this->assertStringNotContainsString('images/defaults', json_encode($seo->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_dark_only_logo_is_used_on_a_dark_background_never_the_default(): void
    {
        $this->branding(logoPath: 'settings/dark.png', logoIsDark: true);

        $seo = $this->factory()->forEvent($this->event(null));

        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/w_600,h_600,c_pad,b_black,q_auto,f_png/guildforge/settings/dark',
            $seo->image?->url,
        );
    }

    public function test_default_logo_only_when_the_site_has_none(): void
    {
        $seo = $this->factory()->forEvent($this->event(null));

        $this->assertSame('https://example.com/images/defaults/logo-light.png', $seo->image?->url);
    }

    public function test_article_uses_content_when_there_is_no_excerpt(): void
    {
        $seo = $this->factory()->forArticle(new ArticleResponseDTO(
            id: 'a1',
            title: 'Crónica',
            slug: 'cronica',
            content: '<p>Contenido completo</p>',
            excerpt: null,
            featuredImage: 'articles/cronica.jpg',
            isPublished: true,
            publishedAt: new DateTimeImmutable('2026-02-01T09:00:00Z'),
            author: new AuthorResponseDTO('u1', 'Ana López', 'Ana', null),
            createdAt: null,
            updatedAt: new DateTimeImmutable('2026-02-03T09:00:00Z'),
        ));

        $this->assertSame('article', $seo->type->value);
        $this->assertSame('Contenido completo', $seo->description);
        $this->assertSame('Article', $seo->structuredData[0]['@type']);
        $this->assertSame(['@type' => 'Person', 'name' => 'Ana'], $seo->structuredData[0]['author']);
        $this->assertSame([
            ['property' => 'article:published_time', 'content' => '2026-02-01T10:00:00+01:00'],
            ['property' => 'article:modified_time', 'content' => '2026-02-03T10:00:00+01:00'],
        ], $seo->extraMeta);
    }

    public function test_gallery_uses_its_first_photo(): void
    {
        $seo = $this->factory()->forGallery(new GalleryDetailResponseDTO(
            id: 'g1',
            title: 'Jornadas 2026',
            slug: 'jornadas',
            description: null,
            isPublished: true,
            isFeatured: false,
            photos: [
                new PhotoResponseDTO('p2', 'galleries/second.jpg', null, 2),
                new PhotoResponseDTO('p1', 'galleries/first.jpg', null, 1),
            ],
            createdAt: null,
            updatedAt: null,
        ));

        $this->assertStringEndsWith('/guildforge/galleries/first', (string) $seo->image?->url);
        $this->assertSame('Galería de fotos de la asociación', $seo->description);
        $this->assertSame([], $seo->structuredData);
    }

    public function test_home_has_the_organization(): void
    {
        $seo = $this->factory()->forHome();

        $this->assertSame(SeoMetadata::SCOPE_PAGE, $seo->scope);
        $this->assertSame('Sitio de ejemplo', $seo->fullTitle);
        $this->assertSame('https://example.com', $seo->canonicalUrl);
        $this->assertSame('Organization', $seo->structuredData[0]['@type']);
        $this->assertSame(['https://instagram.com/asociacion-ejemplo'], $seo->structuredData[0]['sameAs']);
    }

    public function test_site_defaults(): void
    {
        $seo = $this->factory()->forSite();

        $this->assertSame(SeoMetadata::SCOPE_SITE, $seo->scope);
        $this->assertSame('Asociación de juegos', $seo->description);
        $this->assertNull($seo->robots);
    }

    public function test_noindex_pages(): void
    {
        $seo = $this->factory()->forPage(new SeoPageDTO(title: 'Mi perfil', noindex: true));

        $this->assertSame('noindex, nofollow', $seo->robots);
    }

    public function test_never_throws_and_falls_back_to_minimal_metadata(): void
    {
        $log = Log::spy();
        $branding = Mockery::mock(SiteBrandingServiceInterface::class);
        $branding->shouldReceive('customLogoPath')->andThrow(new RuntimeException('settings table missing'));
        $this->app->instance(SiteBrandingServiceInterface::class, $branding);

        $seo = $this->factory()->forEvent($this->event('events/club.jpg'));

        $this->assertSame(SeoMetadata::SCOPE_SITE, $seo->scope);
        $this->assertSame('Sitio de ejemplo', $seo->fullTitle);
        $this->assertNull($seo->image);
        $log->shouldHaveReceived('warning')->once();
    }
}
