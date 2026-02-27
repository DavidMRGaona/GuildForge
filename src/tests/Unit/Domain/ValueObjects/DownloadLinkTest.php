<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\DownloadLink;
use PHPUnit\Framework\TestCase;

final class DownloadLinkTest extends TestCase
{
    public function test_it_creates_download_link(): void
    {
        $link = new DownloadLink(
            label: 'Bases del torneo',
            url: 'https://example.com/rules.pdf',
            description: 'Reglas y formato del torneo',
        );

        $this->assertSame('Bases del torneo', $link->label);
        $this->assertSame('https://example.com/rules.pdf', $link->url);
        $this->assertSame('Reglas y formato del torneo', $link->description);
    }

    public function test_it_creates_from_array(): void
    {
        $link = DownloadLink::fromArray([
            'label' => 'Horarios',
            'url' => 'https://example.com/schedule.pdf',
            'description' => 'Horarios del evento',
        ]);

        $this->assertSame('Horarios', $link->label);
        $this->assertSame('https://example.com/schedule.pdf', $link->url);
        $this->assertSame('Horarios del evento', $link->description);
    }

    public function test_it_creates_from_array_with_empty_description(): void
    {
        $link = DownloadLink::fromArray([
            'label' => 'Mapa',
            'url' => 'https://example.com/map.pdf',
            'description' => '',
        ]);

        $this->assertSame('', $link->description);
    }

    public function test_it_converts_to_array(): void
    {
        $link = new DownloadLink(
            label: 'Bases del torneo',
            url: 'https://example.com/rules.pdf',
            description: 'Reglas y formato',
        );

        $this->assertSame([
            'label' => 'Bases del torneo',
            'url' => 'https://example.com/rules.pdf',
            'description' => 'Reglas y formato',
        ], $link->toArray());
    }

    public function test_roundtrip_from_array_to_array(): void
    {
        $data = [
            'label' => 'Test',
            'url' => 'https://example.com/test',
            'description' => 'Test description',
        ];

        $link = DownloadLink::fromArray($data);

        $this->assertSame($data, $link->toArray());
    }
}
