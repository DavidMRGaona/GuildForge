<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Support;

use App\Infrastructure\Support\SanitizesHtml;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class SanitizesHtmlTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SanitizesHtml;

    public function test_it_returns_empty_string_for_empty_input(): void
    {
        $result = $this->sanitizeHtml('');

        $this->assertEquals('', $result);
    }

    public function test_it_strips_script_tags(): void
    {
        $html = '<p>Safe content</p><script>alert("XSS")</script><p>More safe content</p>';

        $result = $this->sanitizeHtml($html);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('<p>Safe content</p>', $result);
        $this->assertStringContainsString('<p>More safe content</p>', $result);
    }

    public function test_it_strips_event_handler_attributes(): void
    {
        $html = '<img src="image.jpg" onerror="alert(\'XSS\')" alt="Test">';

        $result = $this->sanitizeHtml($html);

        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('img', $result);
        $this->assertStringContainsString('alt="Test"', $result);
    }

    public function test_it_strips_onclick_attributes(): void
    {
        $html = '<a href="#" onclick="doSomethingBad()">Click me</a>';

        $result = $this->sanitizeHtml($html);

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('doSomethingBad', $result);
        $this->assertStringContainsString('Click me', $result);
    }

    public function test_it_strips_onload_attributes(): void
    {
        $html = '<body onload="stealData()">Content</body>';

        $result = $this->sanitizeHtml($html);

        $this->assertStringNotContainsString('onload', $result);
        $this->assertStringNotContainsString('stealData', $result);
    }

    public function test_it_strips_javascript_urls_in_href(): void
    {
        $html = '<a href="javascript:alert(\'XSS\')">Click me</a>';

        $result = $this->sanitizeHtml($html);

        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Click me', $result);
    }

    public function test_it_preserves_safe_paragraph_tags(): void
    {
        $html = '<p>This is a paragraph with <strong>bold</strong> and <em>italic</em> text.</p>';

        $result = $this->sanitizeHtml($html);

        $this->assertEquals($html, $result);
    }

    public function test_it_preserves_safe_heading_tags(): void
    {
        $html = '<h1>Heading 1</h1><h2>Heading 2</h2><h3>Heading 3</h3>';

        $result = $this->sanitizeHtml($html);

        $this->assertStringContainsString('<h1>Heading 1</h1>', $result);
        $this->assertStringContainsString('<h2>Heading 2</h2>', $result);
        $this->assertStringContainsString('<h3>Heading 3</h3>', $result);
    }

    public function test_it_preserves_safe_list_tags(): void
    {
        $html = '<ul><li>Item 1</li><li>Item 2</li></ul><ol><li>First</li><li>Second</li></ol>';

        $result = $this->sanitizeHtml($html);

        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>Item 1</li>', $result);
        $this->assertStringContainsString('<ol>', $result);
        $this->assertStringContainsString('<li>First</li>', $result);
    }

    public function test_it_preserves_safe_links_with_http_urls(): void
    {
        $html = '<a href="https://example.com">Safe link</a>';

        $result = $this->sanitizeHtml($html);

        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('Safe link', $result);
    }

    public function test_it_preserves_safe_table_tags(): void
    {
        $html = '<table><tr><td>Cell 1</td><td>Cell 2</td></tr></table>';

        $result = $this->sanitizeHtml($html);

        $this->assertStringContainsString('<table>', $result);
        $this->assertStringContainsString('<tr>', $result);
        $this->assertStringContainsString('<td>Cell 1</td>', $result);
    }

    public function test_it_preserves_safe_image_attributes(): void
    {
        $html = '<img src="https://example.com/image.jpg" alt="Test image">';

        $result = $this->sanitizeHtml($html);

        $this->assertStringContainsString('src="https://example.com/image.jpg"', $result);
        $this->assertStringContainsString('alt="Test image"', $result);
    }

    public function test_it_handles_mixed_safe_and_unsafe_content(): void
    {
        $html = '<p>Safe paragraph</p><script>alert("XSS")</script><p>Another safe paragraph</p><img src="x" onerror="alert(1)">';

        $result = $this->sanitizeHtml($html);

        $this->assertStringContainsString('<p>Safe paragraph</p>', $result);
        $this->assertStringContainsString('<p>Another safe paragraph</p>', $result);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function test_it_keeps_the_alignments_the_panel_editor_writes_on_paragraphs_and_headings(): void
    {
        $html = '<p style="text-align: end">a</p><h2 style="text-align: center">b</h2>'
            .'<h3 style="text-align: start">c</h3><p style="text-align: justify">d</p>';

        $result = $this->sanitizeHtml($html);

        $this->assertStringContainsString('<p style="text-align:end;">a</p>', $result);
        $this->assertStringContainsString('<h2 style="text-align:center;">b</h2>', $result);
        $this->assertStringContainsString('<h3 style="text-align:start;">c</h3>', $result);
        $this->assertStringContainsString('<p style="text-align:justify;">d</p>', $result);
    }

    public function test_it_drops_alignments_that_are_not_alignments(): void
    {
        $result = $this->sanitizeHtml(
            '<p style="text-align: expression(alert(1))">a</p><h2 style="text-align: url(https://e.example/x)">b</h2>'
            .'<h3 style="position: fixed; text-align: end">c</h3>',
        );

        $this->assertStringNotContainsString('expression', $result);
        $this->assertStringNotContainsString('url(', $result);
        $this->assertStringNotContainsString('position', $result);
        $this->assertStringContainsString('<h3 style="text-align:end;">c</h3>', $result);
    }

    public function test_it_keeps_underline_strike_subscript_superscript_and_code_blocks(): void
    {
        $result = $this->sanitizeHtml('<p><u>u</u> <s>s</s> <sub>1</sub> <sup>2</sup></p><pre><code>x &lt; y</code></pre>');

        $this->assertSame('<p><u>u</u> <s>s</s> <sub>1</sub> <sup>2</sup></p><pre><code>x &lt; y</code></pre>', $result);
    }
}
