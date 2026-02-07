<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Support;

use App\Infrastructure\Support\SanitizesHtml;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class SanitizesHtmlTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_returns_empty_string_for_empty_input(): void
    {
        $sanitizer = $this->createSanitizer();

        $result = $sanitizer->clean('');

        $this->assertEquals('', $result);
    }

    public function test_it_strips_script_tags(): void
    {
        $sanitizer = $this->createSanitizer();
        $html = '<p>Safe content</p><script>alert("XSS")</script><p>More safe content</p>';

        $result = $sanitizer->clean($html);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('<p>Safe content</p>', $result);
        $this->assertStringContainsString('<p>More safe content</p>', $result);
    }

    public function test_it_strips_event_handler_attributes(): void
    {
        $sanitizer = $this->createSanitizer();
        $html = '<img src="image.jpg" onerror="alert(\'XSS\')" alt="Test">';

        $result = $sanitizer->clean($html);

        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('img', $result);
        $this->assertStringContainsString('alt="Test"', $result);
    }

    public function test_it_strips_onclick_attributes(): void
    {
        $sanitizer = $this->createSanitizer();
        $html = '<a href="#" onclick="doSomethingBad()">Click me</a>';

        $result = $sanitizer->clean($html);

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('doSomethingBad', $result);
        $this->assertStringContainsString('Click me', $result);
    }

    public function test_it_strips_onload_attributes(): void
    {
        $sanitizer = $this->createSanitizer();
        $html = '<body onload="stealData()">Content</body>';

        $result = $sanitizer->clean($html);

        $this->assertStringNotContainsString('onload', $result);
        $this->assertStringNotContainsString('stealData', $result);
    }

    public function test_it_strips_javascript_urls_in_href(): void
    {
        $sanitizer = $this->createSanitizer();
        $html = '<a href="javascript:alert(\'XSS\')">Click me</a>';

        $result = $sanitizer->clean($html);

        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Click me', $result);
    }

    public function test_it_preserves_safe_paragraph_tags(): void
    {
        $sanitizer = $this->createSanitizer();
        $html = '<p>This is a paragraph with <strong>bold</strong> and <em>italic</em> text.</p>';

        $result = $sanitizer->clean($html);

        $this->assertEquals($html, $result);
    }

    public function test_it_preserves_safe_heading_tags(): void
    {
        $sanitizer = $this->createSanitizer();
        $html = '<h1>Heading 1</h1><h2>Heading 2</h2><h3>Heading 3</h3>';

        $result = $sanitizer->clean($html);

        $this->assertStringContainsString('<h1>Heading 1</h1>', $result);
        $this->assertStringContainsString('<h2>Heading 2</h2>', $result);
        $this->assertStringContainsString('<h3>Heading 3</h3>', $result);
    }

    public function test_it_preserves_safe_list_tags(): void
    {
        $sanitizer = $this->createSanitizer();
        $html = '<ul><li>Item 1</li><li>Item 2</li></ul><ol><li>First</li><li>Second</li></ol>';

        $result = $sanitizer->clean($html);

        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>Item 1</li>', $result);
        $this->assertStringContainsString('<ol>', $result);
        $this->assertStringContainsString('<li>First</li>', $result);
    }

    public function test_it_preserves_safe_links_with_http_urls(): void
    {
        $sanitizer = $this->createSanitizer();
        $html = '<a href="https://example.com">Safe link</a>';

        $result = $sanitizer->clean($html);

        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('Safe link', $result);
    }

    public function test_it_preserves_safe_table_tags(): void
    {
        $sanitizer = $this->createSanitizer();
        $html = '<table><tr><td>Cell 1</td><td>Cell 2</td></tr></table>';

        $result = $sanitizer->clean($html);

        $this->assertStringContainsString('<table>', $result);
        $this->assertStringContainsString('<tr>', $result);
        $this->assertStringContainsString('<td>Cell 1</td>', $result);
    }

    public function test_it_preserves_safe_image_attributes(): void
    {
        $sanitizer = $this->createSanitizer();
        $html = '<img src="https://example.com/image.jpg" alt="Test image">';

        $result = $sanitizer->clean($html);

        $this->assertStringContainsString('src="https://example.com/image.jpg"', $result);
        $this->assertStringContainsString('alt="Test image"', $result);
    }

    public function test_it_handles_mixed_safe_and_unsafe_content(): void
    {
        $sanitizer = $this->createSanitizer();
        $html = '<p>Safe paragraph</p><script>alert("XSS")</script><p>Another safe paragraph</p><img src="x" onerror="alert(1)">';

        $result = $sanitizer->clean($html);

        $this->assertStringContainsString('<p>Safe paragraph</p>', $result);
        $this->assertStringContainsString('<p>Another safe paragraph</p>', $result);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    private function createSanitizer(): object
    {
        return new class
        {
            use SanitizesHtml;

            public function clean(string $html): string
            {
                return $this->sanitizeHtml($html);
            }
        };
    }
}
