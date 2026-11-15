<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Profiles;

use App\Domain\Profiles\ProfileBioText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProfileBioTextTest extends TestCase
{
    public function test_plain_text_is_one_text_segment_per_line(): void
    {
        $this->assertSame([
            [['text' => 'Juego a rol desde 1998.', 'href' => null]],
            [['text' => 'Dirijo los martes.', 'href' => null]],
        ], ProfileBioText::lines("Juego a rol desde 1998.\nDirijo los martes."));
    }

    public function test_line_breaks_of_every_kind_are_kept_and_blank_runs_collapse_to_one(): void
    {
        $this->assertSame([
            [['text' => 'Uno', 'href' => null]],
            [],
            [['text' => 'Dos', 'href' => null]],
            [['text' => 'Tres', 'href' => null]],
        ], ProfileBioText::lines("\n\n  Uno\r\n\r\n\n\nDos\rTres  \n\n"));
    }

    public function test_an_empty_or_blank_text_has_no_lines(): void
    {
        $this->assertSame([], ProfileBioText::lines(''));
        $this->assertSame([], ProfileBioText::lines(" \n\t\n "));
    }

    public function test_http_and_https_addresses_become_links(): void
    {
        $this->assertSame([[
            ['text' => 'Mi blog: ', 'href' => null],
            ['text' => 'https://example.com/blog?a=1&b=2#fin', 'href' => 'https://example.com/blog?a=1&b=2#fin'],
            ['text' => ' y ', 'href' => null],
            ['text' => 'http://example.org', 'href' => 'http://example.org'],
        ]], ProfileBioText::lines('Mi blog: https://example.com/blog?a=1&b=2#fin y http://example.org'));
    }

    public function test_trailing_punctuation_stays_out_of_the_link(): void
    {
        $this->assertSame([[
            ['text' => '(ver ', 'href' => null],
            ['text' => 'https://example.com/a', 'href' => 'https://example.com/a'],
            ['text' => ').', 'href' => null],
        ]], ProfileBioText::lines('(ver https://example.com/a).'));

        $this->assertSame(
            'https://es.wikipedia.org/wiki/Rol_(juego)',
            ProfileBioText::lines('https://es.wikipedia.org/wiki/Rol_(juego), claro')[0][0]['href'],
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notLinks(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'data' => ['data:text/html,<script>alert(1)</script>'];
        yield 'mailto' => ['mailto:ana@example.com'];
        yield 'protocol relative' => ['//example.com'];
        yield 'no host' => ['https://'];
        yield 'user info' => ['https://bank.example@evil.example/login'];
        yield 'bare domain' => ['example.com'];
    }

    #[DataProvider('notLinks')]
    public function test_anything_but_an_http_or_https_address_stays_text(string $text): void
    {
        foreach (ProfileBioText::lines($text) as $line) {
            foreach ($line as $segment) {
                $this->assertNull($segment['href']);
            }
        }
    }

    public function test_html_is_kept_as_text_and_never_breaks_into_a_link(): void
    {
        $lines = ProfileBioText::lines('<script>alert(1)</script> <a href="javascript:x">hola</a> https://example.com/"onmouseover="alert(1)');

        $this->assertSame([[
            ['text' => '<script>alert(1)</script> <a href="javascript:x">hola</a> ', 'href' => null],
            ['text' => 'https://example.com/', 'href' => 'https://example.com/'],
            ['text' => '"onmouseover="alert(1)', 'href' => null],
        ]], $lines);
    }

    public function test_control_characters_are_dropped(): void
    {
        $this->assertSame([[['text' => "Hola\tmundo", 'href' => null]]], ProfileBioText::lines("Hola\tmun\x00\x07do"));
    }

    public function test_an_overlong_address_stays_text(): void
    {
        $url = 'https://example.com/'.str_repeat('a', 2100);

        $this->assertNull(ProfileBioText::lines($url)[0][0]['href']);
    }
}
