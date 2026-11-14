<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Footer;

use App\Domain\Footer\ValueObjects\FooterBlock;
use App\Domain\Footer\ValueObjects\FooterColumn;
use App\Domain\Footer\ValueObjects\FooterLayout;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FooterLayoutTest extends TestCase
{
    public function test_it_reads_a_stored_layout(): void
    {
        $layout = FooterLayout::fromArray([
            'version' => 1,
            'columns' => [
                ['blocks' => [
                    ['key' => 'core.links', 'title' => 'La asociación', 'settings' => ['menu' => 'footer']],
                    ['key' => 'core.text', 'title' => null, 'settings' => []],
                ]],
                ['blocks' => [['key' => 'core.contact', 'title' => '', 'settings' => ['show_email' => true]]]],
            ],
        ]);

        $this->assertFalse($layout->isEmpty());
        $this->assertCount(2, $layout->columns);
        $this->assertSame('core.links', $layout->columns[0]->blocks[0]->key);
        $this->assertSame('La asociación', $layout->columns[0]->blocks[0]->title);
        $this->assertSame(['menu' => 'footer'], $layout->columns[0]->blocks[0]->settings);
        $this->assertNull($layout->columns[0]->blocks[1]->title);
        // A blank title is no title
        $this->assertNull($layout->columns[1]->blocks[0]->title);
    }

    public function test_it_writes_the_versioned_format(): void
    {
        $layout = FooterLayout::of([
            new FooterColumn([new FooterBlock('core.brand', 'Quiénes somos', ['show_logo' => true])]),
        ]);

        $this->assertSame([
            'version' => 1,
            'columns' => [
                ['blocks' => [['key' => 'core.brand', 'title' => 'Quiénes somos', 'settings' => ['show_logo' => true]]]],
            ],
        ], $layout->toArray());
        $this->assertEquals($layout, FooterLayout::fromArray($layout->toArray()));
    }

    public function test_nothing_readable_is_an_empty_layout(): void
    {
        foreach ([null, '', 'texto', [], ['version' => 2, 'columns' => [['blocks' => [['key' => 'core.text']]]]], ['columns' => 'x']] as $stored) {
            $this->assertTrue(FooterLayout::fromArray($stored)->isEmpty());
        }
    }

    public function test_columns_without_blocks_are_an_empty_layout(): void
    {
        $layout = FooterLayout::fromArray(['version' => 1, 'columns' => [['blocks' => []], ['blocks' => []]]]);

        $this->assertTrue($layout->isEmpty());
    }

    public function test_malformed_blocks_are_left_out(): void
    {
        $layout = FooterLayout::fromArray([
            'version' => 1,
            'columns' => [
                ['blocks' => [
                    'texto',
                    ['title' => 'Sin clave'],
                    ['key' => ''],
                    ['key' => 42],
                    ['key' => 'core.text', 'title' => ['no'], 'settings' => 'no'],
                ]],
                'no es una columna',
            ],
        ]);

        $this->assertCount(1, $layout->columns);
        $this->assertCount(1, $layout->columns[0]->blocks);
        $this->assertNull($layout->columns[0]->blocks[0]->title);
        $this->assertSame([], $layout->columns[0]->blocks[0]->settings);
    }

    public function test_it_keeps_at_most_four_columns_when_reading(): void
    {
        $column = ['blocks' => [['key' => 'core.text']]];

        $layout = FooterLayout::fromArray(['version' => 1, 'columns' => array_fill(0, 6, $column)]);

        $this->assertCount(FooterLayout::MAX_COLUMNS, $layout->columns);
    }

    public function test_it_refuses_more_than_four_columns(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FooterLayout::of(array_fill(0, 5, new FooterColumn([])));
    }

    public function test_titles_are_cut_to_their_length(): void
    {
        $block = new FooterBlock('core.text', str_repeat('á', 120), []);

        $this->assertSame(FooterBlock::MAX_TITLE_LENGTH, mb_strlen((string) $block->title));
    }
}
