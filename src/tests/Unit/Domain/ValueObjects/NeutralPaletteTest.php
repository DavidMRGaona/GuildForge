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

    public function test_dark_backgrounds_are_the_warm_greys_of_the_panel(): void
    {
        $palette = NeutralPalette::palette();

        $this->assertSame('#252220', $palette->shade(950)?->value);
        $this->assertSame('#302D2A', $palette->shade(900)?->value);
        $this->assertSame('#3D3936', $palette->shade(800)?->value);
    }

    public function test_light_backgrounds_are_the_stone_greys(): void
    {
        $palette = NeutralPalette::palette();

        $this->assertSame('#FAFAF9', $palette->shade(50)?->value);
        $this->assertSame('#F5F5F4', $palette->shade(100)?->value);
    }
}
