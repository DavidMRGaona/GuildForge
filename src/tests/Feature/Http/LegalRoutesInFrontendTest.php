<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Http\Request;
use Tests\Support\Files\ReadsFiles;
use Tests\TestCase;

/**
 * The frontend links the legal pages through useRoutes() (core and modules alike), so a consent
 * checkbox never points at a page that does not exist. Its URLs must be the ones the legal.show
 * route serves.
 */
final class LegalRoutesInFrontendTest extends TestCase
{
    use ReadsFiles;

    public function test_use_routes_links_every_legal_page_the_route_serves(): void
    {
        $source = self::readFile(resource_path('js/composables/useRoutes.ts'));

        foreach ([
            'privacy' => 'politica-de-privacidad',
            'notice' => 'aviso-legal',
            'cookies' => 'politica-de-cookies',
            'terms' => 'terminos-y-condiciones',
        ] as $key => $slug) {
            $url = route('legal.show', ['slug' => $slug], absolute: false);

            $this->assertStringContainsString("{$key}: '{$url}'", $source, $key);
            // route() does not check the slug constraint: the router must serve the URL as legal.show
            $this->assertSame('legal.show', app('router')->getRoutes()->match(Request::create($url))->getName(), $url);
        }
    }
}
