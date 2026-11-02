<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Knowledge;

use App\Infrastructure\Knowledge\Search\SnippetBuilder;
use PHPUnit\Framework\TestCase;

final class SnippetBuilderTest extends TestCase
{
    public function test_short_texts_are_kept_whole_with_matches_marked(): void
    {
        $this->assertSame(
            [
                ['text' => 'La ', 'match' => false],
                ['text' => 'Inscripción', 'match' => true],
                ['text' => ' y otra ', 'match' => false],
                ['text' => 'inscripcion', 'match' => true],
                ['text' => '.', 'match' => false],
            ],
            (new SnippetBuilder)->build('La Inscripción y otra inscripcion.', ['inscripcion']),
        );
    }

    public function test_long_texts_are_cut_around_the_first_match_with_ellipses(): void
    {
        $text = str_repeat('antes ', 30).'objetivo'.str_repeat(' después', 30);

        $snippet = (new SnippetBuilder)->build($text, ['objetivo'], 20);
        $joined = implode('', array_column($snippet, 'text'));

        $this->assertStringStartsWith('…', $joined);
        $this->assertStringEndsWith('…', $joined);
        $this->assertStringContainsString('objetivo', $joined);
        $this->assertLessThan(60, mb_strlen($joined));
        $this->assertContains(['text' => 'objetivo', 'match' => true], $snippet);
    }

    public function test_without_a_match_the_beginning_is_returned(): void
    {
        $snippet = (new SnippetBuilder)->build(str_repeat('palabra ', 40), ['nada'], 10);

        $this->assertCount(1, $snippet);
        $this->assertFalse($snippet[0]['match']);
        $this->assertStringStartsWith('palabra', $snippet[0]['text']);
        $this->assertStringEndsWith('…', $snippet[0]['text']);
    }

    public function test_markup_like_text_is_returned_as_text(): void
    {
        $snippet = (new SnippetBuilder)->build('Usa <script>alert(1)</script> con cuidado', ['cuidado']);

        $this->assertSame('Usa <script>alert(1)</script> con ', $snippet[0]['text']);
        $this->assertSame(['text' => 'cuidado', 'match' => true], $snippet[1]);
    }

    public function test_line_breaks_are_collapsed(): void
    {
        $snippet = (new SnippetBuilder)->build("Primera línea\n\nSegunda   línea", ['segunda']);

        $this->assertSame('Primera línea ', $snippet[0]['text']);
    }

    public function test_empty_text_gives_no_segments(): void
    {
        $this->assertSame([], (new SnippetBuilder)->build('', ['algo']));
    }
}
