<?php

declare(strict_types=1);

namespace Tests\Unit\Application\QrCodes;

use App\Application\QrCodes\SvgLogoSanitizer;
use App\Domain\QrCodes\Exceptions\InvalidQrCodeOptionsException;
use DOMDocument;
use DOMElement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SvgLogoSanitizerTest extends TestCase
{
    private const string SVG = 'http://www.w3.org/2000/svg';

    public function test_it_keeps_a_plain_logo_and_reads_its_size_from_the_view_box(): void
    {
        $logo = SvgLogoSanitizer::sanitize(
            '<svg xmlns="'.self::SVG.'" viewBox="0 0 200 100"><defs><linearGradient id="g"><stop offset="0" stop-color="#C81E1E"/></linearGradient></defs>'
            .'<path id="p" d="M0 0h200v100H0z" fill="url(#g)" style="stroke: url(#g); stroke-width: 2"/><use href="#p" x="10"/></svg>'
        );

        $this->assertSame(200.0, $logo->width);
        $this->assertSame(100.0, $logo->height);

        $svg = $this->parse($logo->contents);
        $this->assertSame(self::SVG, $svg->namespaceURI);
        $this->assertSame('0 0 200 100', $svg->getAttribute('viewBox'));
        $path = $this->first($svg, 'path');
        $this->assertSame('url(#g)', $path->getAttribute('fill'));
        $this->assertSame('stroke: url(#g); stroke-width: 2', $path->getAttribute('style'));
        $this->assertSame('#p', $this->first($svg, 'use')->getAttribute('href'));
        $this->assertSame(1, $svg->getElementsByTagName('stop')->length);
    }

    public function test_it_drops_scripts_event_handlers_and_foreign_content(): void
    {
        $logo = SvgLogoSanitizer::sanitize(
            '<?xml version="1.0"?><?xml-stylesheet href="https://evil.example.com/a.css"?>'
            .'<svg xmlns="'.self::SVG.'" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" '
            .'viewBox="0 0 10 10" onload="alert(1)" inkscape:export-filename="/home/someone/logo.png">'
            .'<!-- a comment --><sodipodi:namedview id="n"/><script>alert(2)</script>'
            .'<foreignObject width="10" height="10"><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject>'
            .'<a href="https://evil.example.com/"><rect width="1" height="1"/></a>'
            .'<rect width="10" height="10" ONCLICK="alert(3)" inkscape:label="Fondo" fill="#C81E1E"/></svg>'
        );

        $this->assertStringNotContainsStringIgnoringCase('alert', $logo->contents);
        $this->assertStringNotContainsString('evil.example.com', $logo->contents);
        $this->assertStringNotContainsString('/home/someone', $logo->contents);
        $this->assertStringNotContainsString('namedview', $logo->contents);
        $this->assertStringNotContainsString('comment', $logo->contents);
        $this->assertStringNotContainsString('<?', $logo->contents);

        $svg = $this->parse($logo->contents);
        $this->assertSame(0, $svg->getElementsByTagName('script')->length);
        $this->assertSame(0, $svg->getElementsByTagName('foreignObject')->length);
        $this->assertSame(0, $svg->getElementsByTagName('a')->length);
        $this->assertSame(1, $svg->getElementsByTagName('rect')->length);
        $this->assertSame('#C81E1E', $this->first($svg, 'rect')->getAttribute('fill'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function externalReferences(): iterable
    {
        yield 'image over http' => ['<image width="10" height="10" href="http://127.0.0.1:9/x.png"/>'];
        yield 'image from a file' => ['<image width="10" height="10" href="file:///etc/passwd"/>'];
        yield 'image by xlink' => ['<image width="10" height="10" xlink:href="https://evil.example.com/x.png"/>'];
        yield 'relative image' => ['<image width="10" height="10" href="../../storage/app/secret.png"/>'];
        yield 'nested svg image' => ['<image width="10" height="10" href="data:image/svg+xml;base64,'.base64_encode('<svg xmlns="'.self::SVG.'"><image href="file:///etc/passwd"/></svg>').'"/>'];
        yield 'use of another file' => ['<use href="other.svg#shape"/>'];
        yield 'use of a file by xlink' => ['<use xlink:href="file:///tmp/a.svg#x"/>'];
        yield 'gradient chained to a file' => ['<linearGradient id="g" href="https://evil.example.com/g.svg#g"/>'];
        yield 'fill from another document' => ['<rect width="1" height="1" fill="url(https://evil.example.com/g.svg#g)"/>'];
        yield 'filter from a file' => ['<rect width="1" height="1" filter="url(\'file:///etc/f.svg#f\')"/>'];
        yield 'style url' => ['<rect width="1" height="1" style="fill: url(&quot;http://evil.example.com/a.svg#a&quot;)"/>'];
        yield 'style image-set' => ['<rect width="1" height="1" style="fill: image-set(&quot;https://evil.example.com/a.png&quot; 1x)"/>'];
        yield 'escaped url' => ['<rect width="1" height="1" style="fill: u\72l(http://evil.example.com/a.svg#a)"/>'];
        yield 'cursor' => ['<rect width="1" height="1" cursor="url(https://evil.example.com/c.cur), auto"/>'];
        yield 'style import' => ['<style>@import url(https://evil.example.com/a.css); rect { fill: red }</style>'];
        yield 'style sheet url' => ['<style>rect { fill: url(https://evil.example.com/a.svg#a) }</style>'];
        yield 'xinclude' => ['<xi:include xmlns:xi="http://www.w3.org/2001/XInclude" href="file:///etc/passwd" parse="text"/>'];
        yield 'fe image' => ['<filter id="f"><feImage href="https://evil.example.com/x.png"/></filter>'];
    }

    #[DataProvider('externalReferences')]
    public function test_it_drops_every_reference_outside_the_document(string $element): void
    {
        $logo = SvgLogoSanitizer::sanitize(
            '<svg xmlns="'.self::SVG.'" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">'.$element.'<rect width="10" height="10"/></svg>'
        );

        // Namespace declarations are names, not references
        $markup = (string) preg_replace('/\sxmlns(?::\w+)?="[^"]*"/', '', $logo->contents);

        foreach (['http:', 'https:', 'file:', '127.0.0.1', 'evil.example.com', 'etc/', 'secret', 'other.svg', 'svg+xml', 'include', '@import'] as $reference) {
            $this->assertStringNotContainsStringIgnoringCase($reference, $markup);
        }

        // The rest of the logo stays
        $this->assertGreaterThanOrEqual(1, $this->parse($logo->contents)->getElementsByTagName('rect')->length);
    }

    public function test_it_drops_attributes_with_an_undeclared_prefix(): void
    {
        // libxml only warns about an undeclared prefix and keeps the attribute without a namespace
        $logo = SvgLogoSanitizer::sanitize(
            '<svg xmlns="'.self::SVG.'" viewBox="0 0 10 10"><image width="10" height="10" xlink:href="https://evil.example.com/x.png"/>'
            .'<rect width="10" height="10" xml:base="https://evil.example.com/" foo:bar="1"/></svg>'
        );

        $this->assertStringNotContainsString('evil.example.com', $logo->contents);
        $this->assertStringNotContainsString('foo:bar', $logo->contents);
    }

    public function test_it_keeps_embedded_raster_images_and_fragment_references(): void
    {
        $png = 'data:image/png;base64,iVBORw0KGgo=';

        $logo = SvgLogoSanitizer::sanitize(
            '<svg xmlns="'.self::SVG.'" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">'
            .'<image width="10" height="10" xlink:href="'.$png.'"/><use xlink:href="#a"/><style>rect { fill: url(#g) }</style></svg>'
        );

        $svg = $this->parse($logo->contents);
        $this->assertSame($png, $this->first($svg, 'image')->getAttributeNS('http://www.w3.org/1999/xlink', 'href'));
        $this->assertSame('#a', $this->first($svg, 'use')->getAttributeNS('http://www.w3.org/1999/xlink', 'href'));
        $this->assertStringContainsString('rect { fill: url(#g) }', $this->first($svg, 'style')->textContent);
    }

    /**
     * @return iterable<string, array{string, float, float}>
     */
    public static function sizes(): iterable
    {
        yield 'view box with commas' => ['viewBox="-5,-5,40,20"', 40.0, 20.0];
        yield 'view box wins over width and height' => ['viewBox="0 0 30 10" width="1" height="1"', 30.0, 10.0];
        yield 'width and height in pixels' => ['width="120px" height="60"', 120.0, 60.0];
        yield 'width and height in centimetres' => ['width="3cm" height="1.5cm"', 3 * 96 / 2.54, 1.5 * 96 / 2.54];
        yield 'mixed units' => ['width="1in" height="48pt"', 96.0, 64.0];
    }

    #[DataProvider('sizes')]
    public function test_it_reads_the_size_from_the_view_box_or_the_dimensions(string $attributes, float $width, float $height): void
    {
        $logo = SvgLogoSanitizer::sanitize('<svg xmlns="'.self::SVG.'" '.$attributes.'><rect width="1" height="1"/></svg>');

        $this->assertEqualsWithDelta($width, $logo->width, 0.0001);
        $this->assertEqualsWithDelta($height, $logo->height, 0.0001);
    }

    public function test_the_logo_scales_to_any_box_by_its_view_box(): void
    {
        $logo = SvgLogoSanitizer::sanitize('<svg xmlns="'.self::SVG.'" width="3cm" height="1.5cm"><rect width="1" height="1"/></svg>');

        $svg = $this->parse($logo->contents);
        // Width and height would fix its size in an <image> box; the viewBox keeps the proportion
        $this->assertFalse($svg->hasAttribute('width'));
        $this->assertFalse($svg->hasAttribute('height'));
        $viewBox = array_map(floatval(...), preg_split('/[\s,]+/', $svg->getAttribute('viewBox')) ?: []);
        $this->assertCount(4, $viewBox);
        $this->assertEqualsWithDelta(2.0, $viewBox[2] / $viewBox[3], 0.0001);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableSvgs(): iterable
    {
        $svg = self::SVG;

        yield 'entities' => ['<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="'.$svg.'" viewBox="0 0 1 1"><text>&x;</text></svg>'];
        yield 'internal entities' => ['<!DOCTYPE svg [<!ENTITY a "aaaaaaaaaa"><!ENTITY b "&a;&a;&a;&a;&a;">]><svg xmlns="'.$svg.'" viewBox="0 0 1 1"><text>&b;</text></svg>'];
        yield 'doctype only' => ['<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg xmlns="'.$svg.'" viewBox="0 0 1 1"/>'];
        yield 'not xml' => ['<svg xmlns="'.$svg.'" viewBox="0 0 1 1"><rect></svg>'];
        yield 'not svg' => ['<html xmlns="http://www.w3.org/1999/xhtml"><svg xmlns="'.$svg.'" viewBox="0 0 1 1"/></html>'];
        yield 'no namespace' => ['<svg viewBox="0 0 1 1"><rect width="1" height="1"/></svg>'];
        yield 'no size' => ['<svg xmlns="'.$svg.'"><rect width="1" height="1"/></svg>'];
        yield 'percentages' => ['<svg xmlns="'.$svg.'" width="100%" height="50%"/>'];
        yield 'empty view box' => ['<svg xmlns="'.$svg.'" viewBox="0 0 0 10"/>'];
        yield 'negative view box' => ['<svg xmlns="'.$svg.'" viewBox="0 0 -10 10"/>'];
        yield 'broken view box' => ['<svg xmlns="'.$svg.'" viewBox="0 0 ten 10"/>'];
        yield 'empty' => [''];
    }

    #[DataProvider('unusableSvgs')]
    public function test_it_rejects_svgs_it_cannot_use_safely(string $contents): void
    {
        $this->expectException(InvalidQrCodeOptionsException::class);

        SvgLogoSanitizer::sanitize($contents);
    }

    private function parse(string $contents): DOMElement
    {
        $this->assertStringStartsWith('<svg', $contents);

        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($contents, LIBXML_NONET));
        $this->assertNull($document->doctype);
        $this->assertInstanceOf(DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }

    private function first(DOMElement $svg, string $name): DOMElement
    {
        $element = $svg->getElementsByTagName($name)->item(0);
        $this->assertInstanceOf(DOMElement::class, $element);

        return $element;
    }
}
