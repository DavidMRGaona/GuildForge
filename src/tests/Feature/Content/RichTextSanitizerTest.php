<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Application\Content\Services\RichTextSanitizerInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * HTML written in the web rich editor (core#573): sanitised with the core's pipeline and still
 * something the panel's RichEditor opens and saves without loss.
 */
final class RichTextSanitizerTest extends TestCase
{
    private const string PATH = 'articles/content/2026/11/3f2b8c1e-0d4a-4f6e-9a7b-1c2d3e4f5a6b.jpg';

    private RichTextSanitizerInterface $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        $this->sanitizer = $this->app->make(RichTextSanitizerInterface::class);
    }

    private function clean(string $html): string
    {
        return $this->sanitizer->sanitize($html, EditorImageOrigin::Articles);
    }

    public function test_it_removes_scripts_handlers_and_dangerous_links(): void
    {
        $html = $this->clean(
            '<p onclick="x()">Hola<script>alert(1)</script></p><a href="javascript:alert(1)">mal</a>'
            .'<iframe src="https://evil.example"></iframe><style>p{}</style><a href="https://ok.example/">bien</a>',
        );

        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('iframe', $html);
        $this->assertStringNotContainsString('<style', $html);
        $this->assertStringContainsString('href="https://ok.example/"', $html);
        $this->assertStringContainsString('rel="', $html);
    }

    public function test_it_drops_the_empty_paragraphs_the_editor_leaves_at_the_end(): void
    {
        $this->assertSame('<p>x</p>', $this->clean('<p>x</p><p></p><p> </p>'));
        $this->assertSame('', $this->clean('<p></p>'));
    }

    public function test_it_keeps_the_editor_toolset(): void
    {
        $html = $this->clean('<h2>T</h2><h3>S</h3><p><strong>a</strong> <em>b</em></p><ul><li><p>x</p></li></ul><ol><li><p>y</p></li></ol><blockquote><p>q</p></blockquote>');

        foreach (['<h2>', '<h3>', '<strong>', '<em>', '<ul>', '<ol>', '<li>', '<blockquote>'] as $tag) {
            $this->assertStringContainsString($tag, $html);
        }
    }

    public function test_images_of_the_origin_keep_their_data_id_so_the_panel_knows_them(): void
    {
        $url = Storage::disk('images')->url(self::PATH);

        $html = $this->clean('<p>x</p><img src="'.$url.'" data-id="'.self::PATH.'" alt="Foto">');

        $this->assertStringContainsString('data-id="'.self::PATH.'"', $html);
        $this->assertStringContainsString('src="'.$url.'"', $html);
        $this->assertStringContainsString('alt="Foto"', $html);
    }

    public function test_images_that_are_not_the_origins_own_are_dropped(): void
    {
        $url = Storage::disk('images')->url(self::PATH);
        $other = 'events/content/2026/11/3f2b8c1e-0d4a-4f6e-9a7b-1c2d3e4f5a6b.jpg';

        $html = $this->clean(
            '<img src="https://tracker.example/p.gif">'
            .'<img src="'.Storage::disk('images')->url($other).'" data-id="'.$other.'">'
            .'<img src="https://tracker.example/q.gif" data-id="'.self::PATH.'">',
        );
        $html .= $this->clean('<img src="'.$url.'">');

        $this->assertStringNotContainsString('tracker.example', $html);
        $this->assertStringNotContainsString('events/content', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_the_panel_editor_reads_the_html_and_gives_it_back_unchanged(): void
    {
        $url = Storage::disk('images')->url(self::PATH);
        $html = $this->clean(
            '<h2>Título</h2><p>Hola <strong>negrita</strong> <em>cursiva</em> <a href="https://example.com/x">enlace</a></p>'
            .'<ul><li><p>uno</p></li><li><p>dos</p></li></ul><ol><li><p>a</p></li></ol>'
            .'<blockquote><p>cita</p></blockquote><img src="'.$url.'" data-id="'.self::PATH.'" alt="foto">',
        );

        $document = RichContentRenderer::make($html)->toArray();
        $types = array_column($document['content'], 'type');

        $this->assertSame(['heading', 'paragraph', 'bulletList', 'orderedList', 'blockquote', 'image'], $types);
        $this->assertSame(self::PATH, $document['content'][5]['attrs']['id']);
        $this->assertSame('foto', $document['content'][5]['attrs']['alt']);

        $link = $document['content'][1]['content'][5]['marks'][0];
        $this->assertSame('link', $link['type']);
        $this->assertSame('https://example.com/x', $link['attrs']['href']);

        // Nothing the panel would lose: reading its own output again gives the same document (it
        // rebuilds an image's "src" from its id when it renders, so only that is left out)
        $again = RichContentRenderer::make(RichContentRenderer::make($html)->toHtml())->toArray();
        unset($document['content'][5]['attrs']['src']);
        $this->assertSame($document, $again);
    }
}
