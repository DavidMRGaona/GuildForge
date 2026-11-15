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

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function quotedAddresses(): iterable
    {
        yield 'angle quotes' => ['Mira «https://example.com».', 'https://example.com', '».'];
        yield 'curly double quotes' => ['Mira “https://example.com/a”', 'https://example.com/a', '”'];
        yield 'curly single quotes' => ['Mira ‘https://example.com/a’', 'https://example.com/a', '’'];
        yield 'bracket and quote' => ['(«https://example.com/a»)', 'https://example.com/a', '»)'];
        yield 'bracket before quote' => ['«ver https://example.com/a)»', 'https://example.com/a', ')»'];
        yield 'ellipsis' => ['Mi web: https://example.com/a…', 'https://example.com/a', '…'];
    }

    #[DataProvider('quotedAddresses')]
    public function test_quotes_and_an_ellipsis_around_an_address_stay_out_of_the_link(string $text, string $href, string $after): void
    {
        $link = null;
        $last = null;
        foreach (ProfileBioText::lines($text)[0] as $segment) {
            if ($segment['href'] !== null) {
                $link = $segment;
            }
            $last = $segment;
        }

        $this->assertSame(['text' => $href, 'href' => $href], $link);
        $this->assertSame($after, $last['text']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function oddHosts(): iterable
    {
        yield 'backslash' => ['https://example.com\\@evil.example/'];
        yield 'backslash in the path' => ['https://example.com/a\\b'];
        yield 'unicode host' => ['https://exámple.com/'];
        yield 'empty label' => ['https://example..com/'];
        yield 'underscore' => ['https://ex_ample.com/'];
    }

    #[DataProvider('oddHosts')]
    public function test_an_address_with_a_backslash_or_an_odd_host_stays_text(string $text): void
    {
        foreach (ProfileBioText::lines($text)[0] as $segment) {
            $this->assertNull($segment['href']);
        }
    }

    public function test_an_international_name_links_in_its_punycode_form(): void
    {
        $this->assertSame('https://xn--exmple-cua.com/', ProfileBioText::lines('https://xn--exmple-cua.com/')[0][0]['href']);
    }

    public function test_direction_overrides_are_dropped(): void
    {
        $this->assertSame(
            [[['text' => 'Hola ', 'href' => null], ['text' => 'https://example.com/txt.exe', 'href' => 'https://example.com/txt.exe']]],
            ProfileBioText::lines("Hola \u{202E}https://example.com/txt\u{2066}.exe\u{2069}\u{200F}"),
        );
    }

    public function test_the_text_is_cut_to_the_bio_limit(): void
    {
        $lines = ProfileBioText::lines(str_repeat('á', 600));

        $this->assertSame(ProfileBioText::MAX_LENGTH, mb_strlen($lines[0][0]['text']));
    }

    public function test_control_characters_are_dropped(): void
    {
        $this->assertSame([[['text' => "Hola\tmundo", 'href' => null]]], ProfileBioText::lines("Hola\tmun\x00\x07do"));
    }

    public function test_an_address_past_the_limit_is_cut_with_the_text(): void
    {
        $url = 'https://example.com/'.str_repeat('a', 600);

        $this->assertSame(substr($url, 0, ProfileBioText::MAX_LENGTH), ProfileBioText::lines($url)[0][0]['href']);
    }
}
