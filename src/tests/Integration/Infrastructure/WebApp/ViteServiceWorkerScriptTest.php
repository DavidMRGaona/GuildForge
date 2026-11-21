<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\WebApp;

use App\Application\Services\SettingsServiceInterface;
use App\Application\WebApp\Services\WebAppManifestInterface;
use App\Infrastructure\WebApp\ViteServiceWorkerScript;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class ViteServiceWorkerScriptTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $buildDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildDirectory = 'test-build-'.Str::lower(Str::random(12));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path($this->buildDirectory));

        parent::tearDown();
    }

    public function test_it_serves_the_built_worker_after_its_settings(): void
    {
        $this->build('self.addEventListener("push",()=>{});');
        app(SettingsServiceInterface::class)->set('guild_name', 'Asociación de ejemplo');

        $script = (string) $this->worker()->script();

        $this->assertStringEndsWith("\n".'self.addEventListener("push",()=>{});', $script);
        $this->assertStringStartsWith('self.serviceWorkerSettings = {', $script);
        $settings = $this->settings($script);
        $this->assertSame(__('web_app.service_worker.fallback_title'), $settings['fallbackTitle']);
        $this->assertSame('Asociación de ejemplo', $settings['fallbackBody']);
        $this->assertSame('/avisos', $settings['fallbackPath']);
        $this->assertSame('/favicons/light/android-chrome-192x192.png', $settings['icon']);
    }

    public function test_settings_cannot_close_the_script_tag_or_the_statement(): void
    {
        $this->build('void 0;');
        app(SettingsServiceInterface::class)->set('guild_name', '</script><script>alert(1)</script>";x=1;//');

        $firstLine = strtok((string) $this->worker()->script(), "\n");

        $this->assertStringNotContainsString('</script>', (string) $firstLine);
        $this->assertSame('</script><script>alert(1)</script>";x=1;//', $this->settings((string) $firstLine)['fallbackBody']);
    }

    public function test_it_is_null_and_warns_when_its_settings_cannot_be_built(): void
    {
        $this->build('void 0;');
        $manifest = Mockery::mock(WebAppManifestInterface::class);
        $manifest->shouldReceive('manifest', 'notificationIcon')->andThrow(new RuntimeException('secret detail'));
        $this->app->instance(WebAppManifestInterface::class, $manifest);
        $log = Log::spy();

        // The route answers its quiet 404 rather than a 500
        $this->assertNull($this->worker()->script());
        $log->shouldHaveReceived('warning')->once()->with(
            'Service worker settings could not be built',
            ['exception' => RuntimeException::class],
        );
    }

    public function test_it_is_null_without_a_build(): void
    {
        $this->assertNull($this->worker()->script());
    }

    public function test_it_is_null_when_the_build_has_no_service_worker(): void
    {
        File::ensureDirectoryExists(public_path($this->buildDirectory));
        File::put(public_path($this->buildDirectory.'/manifest.json'), '{}');

        $this->assertNull($this->worker()->script());
    }

    private function worker(): ViteServiceWorkerScript
    {
        // The base test case swaps Vite for a fake that reads nothing: this one reads the test build
        return app()->make(ViteServiceWorkerScript::class, ['vite' => new Vite, 'buildDirectory' => $this->buildDirectory]);
    }

    private function build(string $source): void
    {
        $directory = public_path($this->buildDirectory);
        File::ensureDirectoryExists($directory.'/assets');
        File::put($directory.'/assets/abc123.js', $source);
        File::put($directory.'/manifest.json', (string) json_encode([
            ViteServiceWorkerScript::ENTRY => ['file' => 'assets/abc123.js', 'src' => ViteServiceWorkerScript::ENTRY, 'isEntry' => true],
        ]));
    }

    /**
     * @return array<string, string>
     */
    private function settings(string $script): array
    {
        $line = (string) strtok($script, "\n");
        $json = substr($line, strlen('self.serviceWorkerSettings = '), -1);

        /** @var array<string, string> $settings */
        $settings = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $settings;
    }
}
