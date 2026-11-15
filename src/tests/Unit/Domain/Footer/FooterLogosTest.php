<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Footer;

use App\Domain\Footer\FooterLogos;
use PHPUnit\Framework\TestCase;

final class FooterLogosTest extends TestCase
{
    public function test_it_reads_the_logos_with_an_image_in_order(): void
    {
        $items = FooterLogos::itemsIn(['logos' => [
            'a' => ['image' => 'footer/logos/a.png', 'alt' => ' Federación ', 'url' => 'https://example.com'],
            'b' => ['image' => 'events/b.png', 'alt' => 'Club'],
        ]]);

        $this->assertSame([
            ['image' => 'footer/logos/a.png', 'alt' => 'Federación', 'url' => 'https://example.com'],
            ['image' => 'events/b.png', 'alt' => 'Club', 'url' => ''],
        ], $items);
        $this->assertSame(['footer/logos/a.png', 'events/b.png'], FooterLogos::pathsIn(['logos' => [
            ['image' => 'footer/logos/a.png'],
            ['image' => 'events/b.png'],
        ]]));
    }

    public function test_it_ignores_whatever_is_not_a_logo_or_a_stored_path(): void
    {
        $this->assertSame([], FooterLogos::itemsIn([]));
        $this->assertSame([], FooterLogos::itemsIn(['logos' => 'x']));
        $this->assertSame([], FooterLogos::itemsIn(['logos' => [
            'text',
            ['alt' => 'Sin imagen'],
            ['image' => ''],
            ['image' => ['footer/logos/a.png']],
            ['image' => '/etc/passwd'],
            ['image' => '../secret.png'],
            ['image' => 'https://example.com/x.png'],
            ['image' => "footer/logos/a\0.png"],
        ]]));
    }

    public function test_it_keeps_at_most_the_allowed_number(): void
    {
        $logos = array_map(static fn (int $i): array => ['image' => "footer/logos/{$i}.png", 'alt' => 'x'], range(1, 12));

        $this->assertCount(FooterLogos::MAX_LOGOS, FooterLogos::itemsIn(['logos' => $logos]));
    }
}
