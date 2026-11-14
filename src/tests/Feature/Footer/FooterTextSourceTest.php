<?php

declare(strict_types=1);

namespace Tests\Feature\Footer;

use App\Application\Content\DTOs\EditorTextDTO;
use App\Application\Content\Services\EditorTextSourceRegistryInterface;
use App\Application\Footer\Services\FooterLayoutServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Footer\ValueObjects\FooterBlock;
use App\Domain\Footer\ValueObjects\FooterColumn;
use App\Domain\Footer\ValueObjects\FooterLayout;
use App\Infrastructure\Content\Sources\FooterTextSource;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FooterTextSourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_is_registered_with_the_footer_folder(): void
    {
        $source = app(EditorTextSourceRegistryInterface::class)->source('footer');

        $this->assertInstanceOf(FooterTextSource::class, $source);
        $this->assertSame('footer/content/', $source->folder()->claim());
    }

    public function test_it_reads_the_text_blocks_only(): void
    {
        $this->storeLayout();

        $texts = iterator_to_array(app(FooterTextSource::class)->texts(), false);

        $this->assertEquals([
            new EditorTextDTO('0.1', '<p>Uno <img src="/storage/a.png"></p>', 'footer#0.1'),
            new EditorTextDTO('2.0', '<p>Dos</p>', 'footer#2.0'),
        ], $texts);
    }

    public function test_replacing_rewrites_only_that_block(): void
    {
        $this->storeLayout();
        $source = app(FooterTextSource::class);
        [$first] = iterator_to_array($source->texts(), false);

        $this->assertTrue($source->replace($first, '<p>Uno <img src="https://res.example.com/footer/content/a.png"></p>'));

        $layout = app(FooterLayoutServiceInterface::class)->layout();
        $this->assertSame('<p>Uno <img src="https://res.example.com/footer/content/a.png"></p>', $layout->columns[0]->blocks[1]->settings['content']);
        $this->assertSame('<p>Dos</p>', $layout->columns[2]->blocks[0]->settings['content']);
        $this->assertSame(['menu' => 'footer'], $layout->columns[0]->blocks[0]->settings);
        $this->assertSame('Texto', $layout->columns[0]->blocks[1]->title);
    }

    public function test_a_text_changed_meanwhile_is_not_written(): void
    {
        $this->storeLayout();
        $source = app(FooterTextSource::class);
        [$first] = iterator_to_array($source->texts(), false);

        $this->assertFalse($source->replace(new EditorTextDTO($first->id, '<p>Otro texto</p>', $first->label), '<p>Nuevo</p>'));
        $this->assertFalse($source->replace(new EditorTextDTO('0.0', '{"menu":"footer"}', 'footer#0.0'), '<p>Nuevo</p>'));
        $this->assertFalse($source->replace(new EditorTextDTO('9.9', $first->html, 'footer#9.9'), '<p>Nuevo</p>'));
        $this->assertFalse($source->replace(new EditorTextDTO('nada', $first->html, 'footer#nada'), '<p>Nuevo</p>'));

        $this->assertSame($first->html, app(FooterLayoutServiceInterface::class)->layout()->columns[0]->blocks[1]->settings['content']);
    }

    public function test_it_reads_the_table_not_the_settings_cache(): void
    {
        $this->storeLayout();
        app(SettingsServiceInterface::class)->get('footer_layout');
        DB::table('settings')->where('key', 'footer_layout')->update(['value' => '']);

        $this->assertSame([], iterator_to_array(app(FooterTextSource::class)->texts(), false));
    }

    public function test_without_a_layout_there_is_nothing(): void
    {
        $this->assertSame([], iterator_to_array(app(FooterTextSource::class)->texts(), false));
    }

    private function storeLayout(): void
    {
        app(FooterLayoutServiceInterface::class)->save(FooterLayout::of([
            new FooterColumn([
                new FooterBlock('core.links', null, ['menu' => 'footer']),
                new FooterBlock('core.text', 'Texto', ['content' => '<p>Uno <img src="/storage/a.png"></p>']),
            ]),
            new FooterColumn([new FooterBlock('core.text', null, ['content' => ''])]),
            new FooterColumn([new FooterBlock('core.text', null, ['content' => '<p>Dos</p>'])]),
        ]));
    }
}
