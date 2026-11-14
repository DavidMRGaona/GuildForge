<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\NeutralPalette;
use PHPUnit\Framework\TestCase;

final class NeutralPaletteTest extends TestCase
{
    public function test_it_is_a_complete_palette_named_neutral(): void
    {
        $palette = NeutralPalette::palette();

        $this->assertSame('neutral', $palette->name());
        $this->assertSame([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950], array_keys($palette->shades()));
    }

    public function test_every_shade_is_the_listed_hex_value(): void
    {
        foreach (NeutralPalette::palette()->shades() as $shade => $color) {
            $this->assertSame(strtoupper(NeutralPalette::SHADES[$shade]), $color->value, "shade {$shade}");
        }
    }

    public function test_dark_backgrounds_are_a_neutral_page_under_slightly_warm_surfaces(): void
    {
        $palette = NeutralPalette::palette();

        $this->assertSame('#181818', $palette->shade(950)?->value);
        $this->assertSame('#282624', $palette->shade(900)?->value);
        $this->assertSame('#303030', $palette->shade(800)?->value);
        $this->assertSame('#3B3B3B', $palette->shade(700)?->value);
    }

    public function test_light_backgrounds_are_the_stone_greys(): void
    {
        $palette = NeutralPalette::palette();

        $this->assertSame('#FAFAF9', $palette->shade(50)?->value);
        $this->assertSame('#F5F5F4', $palette->shade(100)?->value);
    }
}
