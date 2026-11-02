<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Knowledge;

use App\Infrastructure\Knowledge\Text\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextNormalizerTest extends TestCase
{
    public function test_it_lowercases_and_strips_accents_keeping_length(): void
    {
        $this->assertSame('inscripcion unica', TextNormalizer::normalize('Inscripción ÚNICA'));
    }

    public function test_it_strips_tildes_and_diaeresis(): void
    {
        $this->assertSame('nandu pinguino ano', TextNormalizer::normalize('ÑANDÚ pingüino año'));
    }

    public function test_plain_ascii_is_only_lowercased(): void
    {
        $this->assertSame('hello <b>world</b> 42', TextNormalizer::normalize('Hello <B>World</B> 42'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function awkwardTexts(): iterable
    {
        yield 'decomposed accent' => ["Inscripcio\u{0301}n"];
        yield 'dotted capital i' => ['İstanbul'];
        yield 'sharp s' => ['Straße'];
        yield 'ligature' => ['ﬁn'];
        yield 'emoji' => ['¡Hola 🎲!'];
        yield 'empty' => [''];
    }

    #[DataProvider('awkwardTexts')]
    public function test_length_in_characters_never_changes(string $text): void
    {
        $this->assertSame(mb_strlen($text), mb_strlen(TextNormalizer::normalize($text)));
    }
}
