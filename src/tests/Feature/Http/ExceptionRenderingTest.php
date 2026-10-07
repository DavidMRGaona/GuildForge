<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Tests\TestCase;

final class ExceptionRenderingTest extends TestCase
{
    public function test_api_errors_are_json_without_an_accept_header(): void
    {
        $this->get('/api/no-such-endpoint')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message']);
    }

    public function test_a_wrong_method_on_an_api_route_is_json(): void
    {
        $this->get('/api/webhooks/ses')
            ->assertMethodNotAllowed()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_web_errors_stay_html(): void
    {
        $response = $this->get('/no-such-page');

        $response->assertNotFound();
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
    }
}
