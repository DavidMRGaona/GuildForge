<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * app.ts reads the initial Inertia page from the HTML before Inertia boots,
 * to learn which pages belong to modules and how to build Cloudinary URLs.
 */
final class RootViewInitialPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_root_html_carries_the_initial_page_with_the_props_the_client_boots_from(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $page = $this->initialPage($html);

        $this->assertSame('Home', $page['component']);
        $this->assertArrayHasKey('modulePages', $page['props']);
        $this->assertArrayHasKey('moduleTranslations', $page['props']);
        $this->assertArrayHasKey('cloudinary', $page['props']);
    }

    public function test_the_initial_page_is_a_json_script_element_not_a_data_attribute(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<script data-page="app" type="application/json">', $html);
        $this->assertDoesNotMatchRegularExpression('#<div[^>]*\bdata-page=#', $html);
    }

    /**
     * Decodes the initial page from the JSON script element app.ts reads.
     *
     * @return array{component: string, props: array<string, mixed>}
     */
    private function initialPage(string $html): array
    {
        if (preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $html, $m) !== 1) {
            $this->fail('The root view carries no initial page script element.');
        }

        $page = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($page);

        return $page;
    }
}
