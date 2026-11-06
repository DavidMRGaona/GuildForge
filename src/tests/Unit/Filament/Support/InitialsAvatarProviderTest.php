<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Support;

use App\Filament\Support\InitialsAvatarProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InitialsAvatarProviderTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function names(): array
    {
        return [
            'two words' => ['John Doe', 'JD'],
            'one word' => ['john', 'J'],
            'more than two words' => ['Jane Mary Doe', 'JM'],
            'accented and lower case' => ['ángela éxito', 'ÁÉ'],
            'surrounding and repeated spaces' => ['  Jane   Doe  ', 'JD'],
            'blank' => ['   ', '?'],
        ];
    }

    #[DataProvider('names')]
    public function test_it_draws_the_initials_of_the_name(string $name, string $initials): void
    {
        $this->assertSame($initials, InitialsAvatarProvider::initials($name));
    }

    public function test_the_avatar_is_an_inline_svg_with_the_initials(): void
    {
        $url = InitialsAvatarProvider::svgDataUri('John Doe');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $url);

        $svg = base64_decode(substr($url, strlen('data:image/svg+xml;base64,')), true);
        $this->assertIsString($svg);
        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertStringContainsString('>JD</text>', $svg);
    }

    public function test_markup_in_the_initials_is_escaped(): void
    {
        $url = InitialsAvatarProvider::svgDataUri('<b> &x');
        $svg = (string) base64_decode(substr($url, strlen('data:image/svg+xml;base64,')), true);

        $this->assertStringContainsString('>&lt;&amp;</text>', $svg);
        $this->assertStringNotContainsString('<b', $svg);
    }
}
