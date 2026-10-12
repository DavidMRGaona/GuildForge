<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Seo;

use App\Application\Seo\DTOs\SeoArticleDTO;
use App\Application\Seo\DTOs\SeoImage;
use App\Application\Seo\DTOs\SeoScheduleDTO;
use App\Infrastructure\Seo\SeoSite;
use App\Infrastructure\Seo\StructuredDataBuilder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class StructuredDataBuilderTest extends TestCase
{
    private StructuredDataBuilder $builder;

    private SeoSite $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new StructuredDataBuilder('Europe/Madrid');
        $this->site = new SeoSite(
            name: 'Tolerol',
            url: 'https://tolerol.com',
            description: 'Asociación de juegos',
            logoUrl: 'https://res.cloudinary.com/demo/image/upload/logo',
            sameAs: ['https://instagram.com/tolerol'],
        );
    }

    public function test_event_with_every_optional_field(): void
    {
        $data = $this->builder->event(
            name: 'Club de lectura',
            description: 'Texto',
            url: 'https://tolerol.com/eventos/club',
            schedule: new SeoScheduleDTO(
                start: new DateTimeImmutable('2026-01-15T17:00:00Z'),
                end: new DateTimeImmutable('2026-01-15T21:00:00Z'),
                location: 'La Taberna',
                price: 5.0,
            ),
            image: new SeoImage('https://img/x.jpg', 1200, 630, 'Club', true),
            site: $this->site,
        );

        $this->assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => 'Club de lectura',
            'description' => 'Texto',
            'url' => 'https://tolerol.com/eventos/club',
            'startDate' => '2026-01-15T18:00:00+01:00',
            'endDate' => '2026-01-15T22:00:00+01:00',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'organizer' => ['@type' => 'Organization', 'name' => 'Tolerol', 'url' => 'https://tolerol.com'],
            'image' => ['https://img/x.jpg'],
            'location' => ['@type' => 'Place', 'name' => 'La Taberna', 'address' => 'La Taberna'],
            'offers' => [
                '@type' => 'Offer',
                'price' => '5.00',
                'priceCurrency' => 'EUR',
                'url' => 'https://tolerol.com/eventos/club',
                'availability' => 'https://schema.org/InStock',
            ],
        ], $data);
    }

    public function test_event_without_optional_fields_omits_them_and_ends_when_it_starts(): void
    {
        $data = $this->builder->event(
            name: 'Partida',
            description: 'Texto',
            url: 'https://tolerol.com/mesas/partida',
            schedule: new SeoScheduleDTO(start: new DateTimeImmutable('2026-07-10T16:00:00Z')),
            image: null,
            site: $this->site,
        );

        $this->assertSame('2026-07-10T18:00:00+02:00', $data['endDate']);
        $this->assertArrayNotHasKey('image', $data);
        $this->assertArrayNotHasKey('location', $data);
        $this->assertArrayNotHasKey('offers', $data);
    }

    public function test_free_event_has_a_zero_price_offer(): void
    {
        $data = $this->builder->event(
            name: 'Gratis',
            description: '',
            url: 'https://tolerol.com/eventos/gratis',
            schedule: new SeoScheduleDTO(start: new DateTimeImmutable('2026-07-10T16:00:00Z'), price: 0.0),
            image: null,
            site: $this->site,
        );

        $this->assertSame('0.00', $data['offers']['price']);
    }

    public function test_article(): void
    {
        $data = $this->builder->article(
            headline: 'Crónica del torneo',
            description: 'Resumen',
            url: 'https://tolerol.com/articulos/cronica',
            article: new SeoArticleDTO(
                publishedAt: new DateTimeImmutable('2026-02-01T09:00:00Z'),
                modifiedAt: new DateTimeImmutable('2026-02-03T09:00:00Z'),
                authorName: 'Ana',
            ),
            image: new SeoImage('https://img/a.jpg', 1200, 630, 'Crónica', true),
            site: $this->site,
        );

        $this->assertSame('Article', $data['@type']);
        $this->assertSame('Crónica del torneo', $data['headline']);
        $this->assertSame('2026-02-01T10:00:00+01:00', $data['datePublished']);
        $this->assertSame('2026-02-03T10:00:00+01:00', $data['dateModified']);
        $this->assertSame(['@type' => 'Person', 'name' => 'Ana'], $data['author']);
        $this->assertSame(['https://img/a.jpg'], $data['image']);
        $this->assertSame('https://tolerol.com/articulos/cronica', $data['mainEntityOfPage']);
        $this->assertSame([
            '@type' => 'Organization',
            'name' => 'Tolerol',
            'url' => 'https://tolerol.com',
            'logo' => ['@type' => 'ImageObject', 'url' => 'https://res.cloudinary.com/demo/image/upload/logo'],
        ], $data['publisher']);
    }

    public function test_article_headline_is_capped_at_110_characters_and_modified_defaults_to_published(): void
    {
        $data = $this->builder->article(
            headline: str_repeat('a', 200),
            description: '',
            url: 'https://tolerol.com/articulos/x',
            article: new SeoArticleDTO(publishedAt: new DateTimeImmutable('2026-02-01T09:00:00Z')),
            image: null,
            site: $this->site,
        );

        $this->assertSame(110, mb_strlen($data['headline']));
        $this->assertSame($data['datePublished'], $data['dateModified']);
        $this->assertArrayNotHasKey('author', $data);
        $this->assertArrayNotHasKey('image', $data);
    }

    public function test_organization(): void
    {
        $this->assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Tolerol',
            'url' => 'https://tolerol.com',
            'description' => 'Asociación de juegos',
            'logo' => 'https://res.cloudinary.com/demo/image/upload/logo',
            'sameAs' => ['https://instagram.com/tolerol'],
        ], $this->builder->organization($this->site));
    }

    public function test_organization_without_logo_description_or_profiles(): void
    {
        $data = $this->builder->organization(new SeoSite('Tolerol', 'https://tolerol.com', '', null, []));

        $this->assertSame(['@context', '@type', 'name', 'url'], array_keys($data));
    }
}
