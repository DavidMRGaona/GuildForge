<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Content\Services;

use App\Infrastructure\Content\Services\TrixHtmlConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TrixHtmlConverterTest extends TestCase
{
    #[DataProvider('trixHtml')]
    public function test_trix_markup_becomes_the_html_the_editor_keeps(string $trix, string $expected): void
    {
        $this->assertSame($expected, (new TrixHtmlConverter())->convert($trix));
    }

    #[DataProvider('trixHtml')]
    public function test_converting_twice_changes_nothing(string $trix, string $expected): void
    {
        $this->assertSame($expected, (new TrixHtmlConverter())->convert($expected));
    }

    public function test_empty_content_stays_empty(): void
    {
        $this->assertSame('', (new TrixHtmlConverter())->convert(''));
    }

    /**
     * Filament 3's Trix writes <p> blocks, <p><figure><a><img><figcaption></a></figure></p>
     * attachments and <div class="attachment-gallery"> for two or more images of a block (the
     * cases without "older trix" and the real article); older Trix content used <div> blocks
     * and an <a> around the figure. Attachments live on the public disk
     * (APP_URL/storage/...) and their URLs must come out verbatim.
     *
     * @return array<string, array{string, string}>
     */
    public static function trixHtml(): array
    {
        $image = 'https://gremio.example/storage/Xk2hR7aQmesa.png';
        $pdf = 'https://gremio.example/storage/Q9wLbases.pdf';
        $imageData = '{&quot;contentType&quot;:&quot;image/png&quot;,&quot;filename&quot;:&quot;mesa.png&quot;,&quot;filesize&quot;:2240,&quot;height&quot;:480,&quot;href&quot;:&quot;'.$image.'&quot;,&quot;url&quot;:&quot;'.$image.'&quot;,&quot;width&quot;:640}';
        $pdfData = '{&quot;contentType&quot;:&quot;application/pdf&quot;,&quot;filename&quot;:&quot;bases.pdf&quot;,&quot;filesize&quot;:3880,&quot;href&quot;:&quot;'.$pdf.'&quot;,&quot;url&quot;:&quot;'.$pdf.'&quot;}';
        $captionedImage = '<figure data-trix-attachment="'.$imageData.'" data-trix-content-type="image/png" data-trix-attributes="{&quot;caption&quot;:&quot;Mesa de juego&quot;,&quot;presentation&quot;:&quot;gallery&quot;}" class="attachment attachment--preview attachment--png"><a href="'.$image.'"><img src="'.$image.'" width="640" height="480"><figcaption class="attachment__caption attachment__caption--edited">Mesa de juego</figcaption></a></figure>';
        $plainImage = '<figure data-trix-attachment="'.$imageData.'" data-trix-content-type="image/png" data-trix-attributes="{&quot;presentation&quot;:&quot;gallery&quot;}" class="attachment attachment--preview attachment--png"><a href="'.$image.'"><img src="'.$image.'" width="640" height="480"><figcaption class="attachment__caption"><span class="attachment__name">mesa.png</span> <span class="attachment__size">2.19 KB</span></figcaption></a></figure>';
        $file = '<figure data-trix-attachment="'.$pdfData.'" data-trix-content-type="application/pdf" class="attachment attachment--file attachment--pdf"><a href="'.$pdf.'"><figcaption class="attachment__caption"><span class="attachment__name">bases.pdf</span> <span class="attachment__size">3.79 KB</span></figcaption></a></figure>';
        $fixtures = dirname(__DIR__, 4).'/Fixtures/rich-text';

        return [
            'plain text' => [
                'Empezar en cualquier hobby puede parecer intimidante.',
                '<p>Empezar en cualquier hobby puede parecer intimidante.</p>',
            ],
            // Seeded text keeps its line breaks; a browser shows each one as a space
            'plain text with line breaks' => [
                "Primer párrafo.\n\nSegundo párrafo\ncon un salto.",
                '<p>Primer párrafo. Segundo párrafo con un salto.</p>',
            ],
            'paragraphs and a blank line' => [
                '<p>Primera línea<br>segunda línea</p><p><br></p><p>Segundo párrafo</p>',
                '<p>Primera línea<br>segunda línea</p><p><br></p><p>Segundo párrafo</p>',
            ],
            'div blocks and paragraph breaks' => [
                '<div>Primera línea<br>segunda línea<br><br>Segundo párrafo<br><br></div><div><br></div><div>Tercer bloque</div>',
                '<p>Primera línea<br>segunda línea</p><p>Segundo párrafo</p><p><br></p><p>Tercer bloque</p>',
            ],
            'marks and links' => [
                '<p><strong>negrita</strong> <em>cursiva</em> <span style="text-decoration: underline;">subrayado</span> <del>tachado</del> <a href="https://example.test/a?b=1&amp;c=2">enlace</a></p>',
                '<p><strong>negrita</strong> <em>cursiva</em> <u>subrayado</u> <s>tachado</s> <a href="https://example.test/a?b=1&amp;c=2">enlace</a></p>',
            ],
            'nested lists' => [
                '<ul><li>uno</li><li>dos<ul><li>dos.uno</li></ul></li></ul><ol><li>a</li></ol>',
                '<ul><li><p>uno</p></li><li><p>dos</p><ul><li><p>dos.uno</p></li></ul></li></ul><ol><li><p>a</p></li></ol>',
            ],
            'heading, quote and code' => [
                "<h1>Título</h1><blockquote>cita <strong>larga</strong><br>en dos líneas</blockquote><pre>línea 1\nlínea 2</pre>",
                "<h1>Título</h1><blockquote><p>cita <strong>larga</strong><br>en dos líneas</p></blockquote><pre><code>línea 1\nlínea 2</code></pre>",
            ],
            'image with a caption in a paragraph' => [
                '<p>'.$captionedImage.'</p>',
                '<p><img src="'.$image.'" alt="Mesa de juego"><br><em>Mesa de juego</em></p>',
            ],
            'image without a caption in a paragraph' => [
                '<p>'.$plainImage.'</p>',
                '<p><img src="'.$image.'"></p>',
            ],
            'file in a paragraph' => [
                '<p>'.$file.'</p>',
                '<p><a href="'.$pdf.'">bases.pdf</a></p>',
            ],
            'attachments one after another' => [
                '<p>Antes</p><p>'.$captionedImage.'</p><p>'.$plainImage.'</p><p>'.$file.'</p><p>Después</p>',
                '<p>Antes</p><p><img src="'.$image.'" alt="Mesa de juego"><br><em>Mesa de juego</em></p><p><img src="'.$image.'"></p><p><a href="'.$pdf.'">bases.pdf</a></p><p>Después</p>',
            ],
            'gallery of two images' => [
                '<p>Galería:</p><div class="attachment-gallery attachment-gallery--2">'.$captionedImage.$plainImage.'</div><p>Fin</p>',
                '<p>Galería:</p><p><img src="'.$image.'" alt="Mesa de juego"><br><em>Mesa de juego</em></p><p><img src="'.$image.'"></p><p>Fin</p>',
            ],
            'text around an attachment in a paragraph' => [
                '<p>Antes <em>del</em> mapa'.$captionedImage.'Después</p>',
                '<p>Antes <em>del</em> mapa<img src="'.$image.'" alt="Mesa de juego"><br><em>Mesa de juego</em><br>Después</p>',
            ],
            'caption with a raw greater-than sign' => [
                '<p><figure data-trix-attachment="'.$imageData.'" data-trix-content-type="image/png" data-trix-attributes="{&quot;caption&quot;:&quot;Norte > Sur&quot;}" class="attachment attachment--preview attachment--png"><a href="'.$image.'"><img src="'.$image.'"><figcaption class="attachment__caption attachment__caption--edited">Norte &gt; Sur</figcaption></a></figure></p>',
                '<p><img src="'.$image.'" alt="Norte &gt; Sur"><br><em>Norte &gt; Sur</em></p>',
            ],
            'older trix: image with a caption inside a link' => [
                '<div>Antes<a href="'.$image.'"><figure data-trix-attachment="'.$imageData.'" data-trix-content-type="image/png" data-trix-attributes="{&quot;caption&quot;:&quot;Mesa de juego&quot;}" class="attachment attachment--preview attachment--png"><img src="'.$image.'" width="640" height="480"><figcaption class="attachment__caption attachment__caption--edited">Mesa de juego</figcaption></figure></a>Después</div>',
                '<p>Antes<img src="'.$image.'" alt="Mesa de juego"><br><em>Mesa de juego</em><br>Después</p>',
            ],
            'older trix: image without a caption inside a link' => [
                '<div><a href="'.$image.'"><figure data-trix-attachment="'.$imageData.'" data-trix-content-type="image/png" class="attachment attachment--preview attachment--png"><img src="'.$image.'" width="640" height="480"><figcaption class="attachment__caption"><span class="attachment__name">mesa.png</span> <span class="attachment__size">2.19 KB</span></figcaption></figure></a></div>',
                '<p><img src="'.$image.'"></p>',
            ],
            'older trix: image outside any block' => [
                '<figure data-trix-attachment="'.$imageData.'" data-trix-content-type="image/png" class="attachment attachment--preview"><img src="'.$image.'" width="640" height="480"><figcaption class="attachment__caption">pie</figcaption></figure>',
                '<p><img src="'.$image.'" alt="pie"><br><em>pie</em></p>',
            ],
            'older trix: file inside a link' => [
                '<div>Bases: <a href="'.$pdf.'"><figure data-trix-attachment="'.$pdfData.'" data-trix-content-type="application/pdf" class="attachment attachment--file attachment--pdf"><figcaption class="attachment__caption"><span class="attachment__name">bases.pdf</span> <span class="attachment__size">3.79 KB</span></figcaption></figure></a></div>',
                '<p>Bases: <a href="'.$pdf.'">bases.pdf</a></p>',
            ],
            'html written by the seeders' => [
                "<h2>1. Normativa aplicable</h2>\n<p>La presente política:</p>\n<ul>\n<li>Reglamento (UE) 2016/679.</li>\n</ul>",
                '<h2>1. Normativa aplicable</h2><p>La presente política:</p><ul><li><p>Reglamento (UE) 2016/679.</p></li></ul>',
            ],
            'real article saved by trix' => [
                (string) file_get_contents("{$fixtures}/trix-article.html"),
                rtrim((string) file_get_contents("{$fixtures}/trix-article.expected.html"), "\n"),
            ],
        ];
    }
}
