<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Application\Realtime\Services\RealtimeConfigurationInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Realtime\EnablesRealtime;
use Tests\TestCase;

final class RealtimeConfigurationTest extends TestCase
{
    use EnablesRealtime;

    #[DataProvider('connectionsWithoutRealtime')]
    public function test_it_is_off_without_reverb(string $connection): void
    {
        $this->enableRealtime(['broadcasting.default' => $connection]);

        $this->assertFalse($this->configuration()->enabled());
        $this->assertNull($this->configuration()->client());
        $this->assertNull($this->configuration()->connectSource());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function connectionsWithoutRealtime(): array
    {
        return ['log' => ['log'], 'null' => ['null']];
    }

    public function test_the_browser_connects_to_the_site_host_by_default(): void
    {
        $this->enableRealtime();

        $client = $this->configuration()->client();

        $this->assertTrue($this->configuration()->enabled());
        $this->assertNotNull($client);
        $this->assertSame(self::REALTIME_KEY, $client->key);
        $this->assertSame('example.test', $client->host);
        $this->assertSame(443, $client->port);
        $this->assertTrue($client->tls);
        $this->assertSame('wss://example.test', $this->configuration()->connectSource());
    }

    public function test_a_public_host_port_and_scheme_can_be_given(): void
    {
        $this->enableRealtime([
            'broadcasting.connections.reverb.public.host' => 'WS.Example.test',
            'broadcasting.connections.reverb.public.port' => '6001',
            'broadcasting.connections.reverb.public.scheme' => 'https',
        ]);

        $client = $this->configuration()->client();

        $this->assertNotNull($client);
        $this->assertSame('ws.example.test', $client->host);
        $this->assertSame(6001, $client->port);
        $this->assertSame('wss://ws.example.test:6001', $this->configuration()->connectSource());
    }

    public function test_a_plain_http_site_uses_ws(): void
    {
        $this->enableRealtime(['app.url' => 'http://localhost:8080']);

        $client = $this->configuration()->client();

        $this->assertNotNull($client);
        $this->assertFalse($client->tls);
        $this->assertSame(8080, $client->port);
        $this->assertSame('ws://localhost:8080', $this->configuration()->connectSource());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('incompleteSettings')]
    public function test_incomplete_or_invalid_settings_keep_it_off(array $overrides): void
    {
        $this->enableRealtime($overrides);

        $this->assertFalse($this->configuration()->enabled());
        $this->assertTrue($this->configuration()->isIncomplete());
        $this->assertNull($this->configuration()->connectSource());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function incompleteSettings(): array
    {
        return [
            'no key' => [['broadcasting.connections.reverb.key' => null]],
            'no secret' => [['broadcasting.connections.reverb.secret' => '']],
            'no app id' => [['broadcasting.connections.reverb.app_id' => null]],
            'no server host' => [['broadcasting.connections.reverb.options.host' => null]],
            'a host with a path' => [['broadcasting.connections.reverb.public.host' => 'example.test/ws']],
            'a host with a quote' => [['broadcasting.connections.reverb.public.host' => "example.test'"]],
            'a port out of range' => [['broadcasting.connections.reverb.public.port' => 70000]],
            'another scheme' => [['broadcasting.connections.reverb.public.scheme' => 'ftp']],
            'no site address' => [['app.url' => '']],
        ];
    }

    public function test_settings_without_reverb_are_not_incomplete(): void
    {
        config(['broadcasting.default' => 'log']);

        $this->assertFalse($this->configuration()->isIncomplete());
    }

    private function configuration(): RealtimeConfigurationInterface
    {
        return $this->app->make(RealtimeConfigurationInterface::class);
    }
}
