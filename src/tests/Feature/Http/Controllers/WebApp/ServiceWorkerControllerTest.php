<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\WebApp;

use App\Application\WebApp\Services\ServiceWorkerScriptInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class ServiceWorkerControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string SCRIPT = 'self.addEventListener("push", () => {});';

    public function test_it_serves_the_script_as_javascript_revalidated_on_every_check(): void
    {
        $this->script(self::SCRIPT);

        $response = $this->get('/sw.js');

        $response->assertOk();
        $this->assertSame(self::SCRIPT, $response->getContent());
        $this->assertSame('application/javascript; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_it_sets_no_cookie(): void
    {
        $this->script(self::SCRIPT);

        $this->assertSame([], $this->get('/sw.js')->headers->getCookies());
    }

    public function test_an_unchanged_script_answers_not_modified(): void
    {
        $this->script(self::SCRIPT);
        $etag = (string) $this->get('/sw.js')->headers->get('ETag');

        $this->withHeaders(['If-None-Match' => $etag])->get('/sw.js')->assertStatus(304);
    }

    public function test_it_answers_not_found_when_the_script_was_never_built(): void
    {
        $this->script(null);

        $this->get('/sw.js')->assertNotFound();
    }

    private function script(?string $script): void
    {
        $this->app->instance(ServiceWorkerScriptInterface::class, new readonly class($script) implements ServiceWorkerScriptInterface
        {
            public function __construct(private ?string $script) {}

            public function script(): ?string
            {
                return $this->script;
            }
        });
    }
}
