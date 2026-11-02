<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Search;

use App\Infrastructure\Search\Support\PlainText;
use PHPUnit\Framework\TestCase;

final class PlainTextTest extends TestCase
{
    public function test_it_removes_tags_and_decodes_entities(): void
    {
        $this->assertSame('Café & más', PlainText::from('<p><strong>Caf&eacute;</strong> &amp; m&aacute;s</p>'));
    }

    public function test_it_collapses_whitespace(): void
    {
        $this->assertSame('uno dos tres', PlainText::from("  uno \n\n dos\t tres  "));
    }

    public function test_block_tags_do_not_glue_words_together(): void
    {
        $this->assertSame('Primero Segundo Tercero', PlainText::from('<p>Primero</p><p>Segundo</p><ul><li>Tercero</li></ul>'));
        $this->assertSame('uno dos', PlainText::from('uno<br>dos'));
    }

    public function test_inline_tags_keep_words_whole(): void
    {
        $this->assertSame('palabra', PlainText::from('pala<strong>bra</strong>'));
    }
}
