<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Seo;

use App\Application\Seo\DTOs\SeoImage;
use App\Application\Seo\DTOs\SeoMetadata;
use App\Application\Seo\SeoType;
use PHPUnit\Framework\TestCase;

final class SeoMetadataTest extends TestCase
{
    public function test_to_array_exposes_the_shape_consumed_by_blade_and_the_client(): void
    {
        $metadata = new SeoMetadata(
            scope: SeoMetadata::SCOPE_PAGE,
            title: 'Club de lectura',
            fullTitle: 'Club de lectura - Sitio de ejemplo',
            description: '15 de enero de 2026, 18:00 · La Taberna · Texto',
            canonicalUrl: 'https://example.com/eventos/club',
            type: SeoType::Website,
            siteName: 'Sitio de ejemplo',
            locale: 'es_ES',
            image: new SeoImage('https://res.cloudinary.com/demo/image/upload/x', 1200, 630, 'Club de lectura', true),
            extraMeta: [['property' => 'article:published_time', 'content' => '2026-01-01T10:00:00+01:00']],
            structuredData: [['@type' => 'Event']],
        );

        $this->assertSame([
            'scope' => 'page',
            'title' => 'Club de lectura',
            'fullTitle' => 'Club de lectura - Sitio de ejemplo',
            'description' => '15 de enero de 2026, 18:00 · La Taberna · Texto',
            'canonicalUrl' => 'https://example.com/eventos/club',
            'type' => 'website',
            'siteName' => 'Sitio de ejemplo',
            'locale' => 'es_ES',
            'image' => [
                'url' => 'https://res.cloudinary.com/demo/image/upload/x',
                'width' => 1200,
                'height' => 630,
                'alt' => 'Club de lectura',
                'isLarge' => true,
            ],
            'robots' => null,
            'extraMeta' => [['property' => 'article:published_time', 'content' => '2026-01-01T10:00:00+01:00']],
            'structuredData' => [['@type' => 'Event']],
        ], $metadata->toArray());
    }

    public function test_to_array_without_image_returns_null_image(): void
    {
        $metadata = new SeoMetadata(
            scope: SeoMetadata::SCOPE_SITE,
            title: 'Sitio de ejemplo',
            fullTitle: 'Sitio de ejemplo',
            description: '',
            canonicalUrl: 'https://example.com',
            type: SeoType::Website,
            siteName: 'Sitio de ejemplo',
            locale: 'es_ES',
            image: null,
        );

        $this->assertNull($metadata->toArray()['image']);
        $this->assertSame('site', $metadata->toArray()['scope']);
        $this->assertSame([], $metadata->toArray()['structuredData']);
    }
}
