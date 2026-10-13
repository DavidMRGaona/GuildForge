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

    /**
     * Mirrors extractInitialPageData() in resources/js/app.ts: the JSON script
     * element first, the data-page attribute as the fallback.
     *
     * @return array{component: string, props: array<string, mixed>}
     */
    private function initialPage(string $html): array
    {
        if (preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $html, $m) === 1) {
            $json = $m[1];
        } elseif (preg_match('#<div id="app" data-page="([^"]*)"#', $html, $m) === 1) {
            $json = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        } else {
            $this->fail('The root view carries no initial page.');
        }

        $page = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($page);

        return $page;
    }
}
