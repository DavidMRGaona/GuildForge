<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\SeoHead;
use Tests\TestCase;

final class SeoHeadTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function seo(array $overrides = []): array
    {
        return array_replace([
            'scope' => 'page',
            'title' => 'Club de lectura',
            'fullTitle' => 'Club de lectura - Tolerol',
            'description' => '15 de enero de 2026, 18:00 · Texto',
            'canonicalUrl' => 'https://tolerol.com/eventos/club',
            'type' => 'website',
            'siteName' => 'Tolerol',
            'locale' => 'es_ES',
            'image' => ['url' => 'https://img/club.jpg', 'width' => 1200, 'height' => 630, 'alt' => 'Club de lectura', 'isLarge' => true],
            'robots' => null,
            'extraMeta' => [],
            'structuredData' => [],
        ], $overrides);
    }

    private function render(?array $seo): string
    {
        return (new SeoHead($seo))->render()->render();
    }

    public function test_renders_every_standard_tag(): void
    {
        $html = $this->render($this->seo());

        $this->assertStringContainsString('<title>Club de lectura - Tolerol</title>', $html);
        foreach ([
            '<meta name="description" content="15 de enero de 2026, 18:00 · Texto" data-seo-server>',
            '<link rel="canonical" href="https://tolerol.com/eventos/club" data-seo-server>',
            '<meta property="og:type" content="website" data-seo-server>',
            '<meta property="og:site_name" content="Tolerol" data-seo-server>',
            '<meta property="og:locale" content="es_ES" data-seo-server>',
            '<meta property="og:title" content="Club de lectura" data-seo-server>',
            '<meta property="og:description" content="15 de enero de 2026, 18:00 · Texto" data-seo-server>',
            '<meta property="og:url" content="https://tolerol.com/eventos/club" data-seo-server>',
            '<meta property="og:image" content="https://img/club.jpg" data-seo-server>',
            '<meta property="og:image:width" content="1200" data-seo-server>',
            '<meta property="og:image:height" content="630" data-seo-server>',
            '<meta property="og:image:alt" content="Club de lectura" data-seo-server>',
            '<meta name="twitter:card" content="summary_large_image" data-seo-server>',
            '<meta name="twitter:title" content="Club de lectura" data-seo-server>',
            '<meta name="twitter:description" content="15 de enero de 2026, 18:00 · Texto" data-seo-server>',
            '<meta name="twitter:image" content="https://img/club.jpg" data-seo-server>',
        ] as $tag) {
            $this->assertStringContainsString($tag, $html);
        }
        $this->assertStringNotContainsString('name="robots"', $html);
    }

    public function test_small_card_and_no_dimensions_for_a_logo_of_unknown_size(): void
    {
        $html = $this->render($this->seo([
            'image' => ['url' => 'https://tolerol.com/storage/logo.png', 'width' => null, 'height' => null, 'alt' => 'Tolerol', 'isLarge' => false],
        ]));

        $this->assertStringContainsString('<meta name="twitter:card" content="summary" data-seo-server>', $html);
        $this->assertStringNotContainsString('og:image:width', $html);
    }

    public function test_without_image_there_are_no_image_tags(): void
    {
        $html = $this->render($this->seo(['image' => null]));

        $this->assertStringNotContainsString('og:image', $html);
        $this->assertStringNotContainsString('twitter:image', $html);
        $this->assertStringContainsString('content="summary"', $html);
    }

    public function test_robots_extra_meta_and_json_ld(): void
    {
        $html = $this->render($this->seo([
            'robots' => 'noindex, nofollow',
            'extraMeta' => [['property' => 'article:published_time', 'content' => '2026-02-01T10:00:00+01:00']],
            'structuredData' => [['@context' => 'https://schema.org', '@type' => 'Event', 'name' => 'Club']],
        ]));

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow" data-seo-server>', $html);
        $this->assertStringContainsString('<meta property="article:published_time" content="2026-02-01T10:00:00+01:00" data-seo-server>', $html);
        $this->assertStringContainsString(
            '<script type="application/ld+json" data-seo-server>{"@context":"https://schema.org","@type":"Event","name":"Club"}</script>',
            $html,
        );
    }

    public function test_hostile_text_cannot_break_out_of_attributes_or_the_json_ld_script(): void
    {
        $html = $this->render($this->seo([
            'title' => 'D&D "rol" <b>',
            'structuredData' => [['name' => '</script><script>alert(1)</script>']],
        ]));

        $this->assertStringContainsString('content="D&amp;D &quot;rol&quot; &lt;b&gt;"', $html);
        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $this->assertStringContainsString('</script>', $html);
    }

    public function test_without_seo_prop_falls_back_to_the_site_defaults(): void
    {
        config(['app.name' => 'Tolerol']);

        $this->assertStringContainsString('<title>Tolerol</title>', $this->render(null));
    }
}
