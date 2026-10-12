<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Seo;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RootViewSeoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.name' => 'Tolerol', 'app.description' => 'Asociación de juegos']);
    }

    public function test_pages_without_their_own_metadata_get_the_site_defaults_in_the_html(): void
    {
        $response = $this->get('/calendario');

        $response->assertOk();
        $response->assertSee('<meta property="og:site_name" content="Tolerol" data-seo-server>', false);
        $response->assertSee('<meta name="description" content="Asociación de juegos" data-seo-server>', false);
        $response->assertDontSee('<title inertia>', false);
    }

    public function test_the_seo_prop_reaches_the_client(): void
    {
        $this->get('/calendario')->assertInertia(
            fn (Assert $page) => $page
                ->where('seo.siteName', 'Tolerol')
                ->has('seo.canonicalUrl')
                ->has('seo.structuredData'),
        );
    }
}
