<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Mail\DTOs;

use App\Application\Mail\DTOs\MailDigestLineDTO;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailDigestLineDTOTest extends TestCase
{
    public function test_it_keeps_one_line_and_its_link(): void
    {
        $line = new MailDigestLineDTO("  Ana se ha apuntado\n a «La tumba»  ", 'https://example.test/mesas/1');

        $this->assertSame('Ana se ha apuntado a «La tumba»', $line->text);
        $this->assertSame('https://example.test/mesas/1', $line->url);
    }

    public function test_a_long_text_is_cut_to_the_limit(): void
    {
        $line = new MailDigestLineDTO(str_repeat('á', 250));

        $this->assertSame(MailDigestLineDTO::MAX_LENGTH, mb_strlen($line->text));
        $this->assertStringEndsWith('…', $line->text);
        $this->assertNull($line->url);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function invalid(): iterable
    {
        yield 'empty text' => ["  \n ", null];
        yield 'relative link' => ['Texto', '/mesas/1'];
        yield 'other scheme' => ['Texto', 'javascript:alert(1)'];
        yield 'too long link' => ['Texto', 'https://example.test/'.str_repeat('a', 2048)];
    }

    #[DataProvider('invalid')]
    public function test_it_refuses_what_it_cannot_show(string $text, ?string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MailDigestLineDTO($text, $url);
    }
}
