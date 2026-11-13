<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

use App\Application\Realtime\DTOs\RealtimeClientConfigDTO;
use App\Application\Realtime\Services\RealtimeConfigurationInterface;
use Illuminate\Contracts\Config\Repository;

/**
 * Reads the `reverb` broadcast connection. The application publishes to `options.host`/`port`
 * (usually an internal address); browsers connect to `public.host`/`port`/`scheme`, which default
 * to the site's own address (APP_URL), so one proxy rule sends the WebSocket path to Reverb.
 *
 * Read on every call, never cached: tests and `config:cache` both see the current values.
 */
final readonly class ReverbRealtimeConfiguration implements RealtimeConfigurationInterface
{
    public const string CONNECTION = 'reverb';

    private const string HOST = '/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/D';

    public function __construct(
        private Repository $config,
    ) {}

    public function enabled(): bool
    {
        return $this->client() instanceof RealtimeClientConfigDTO;
    }

    public function client(): ?RealtimeClientConfigDTO
    {
        if (! $this->usesReverb()) {
            return null;
        }

        $connection = 'broadcasting.connections.'.self::CONNECTION;
        $key = $this->string($connection.'.key');

        if ($key === null
            || $this->string($connection.'.secret') === null
            || $this->string($connection.'.app_id') === null
            || $this->string($connection.'.options.host') === null) {
            return null;
        }

        $site = parse_url((string) $this->config->get('app.url', ''));
        $site = is_array($site) ? $site : [];

        $scheme = strtolower($this->string($connection.'.public.scheme') ?? (isset($site['scheme']) ? (string) $site['scheme'] : 'https'));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($this->string($connection.'.public.host') ?? (isset($site['host']) ? (string) $site['host'] : ''));
        if (preg_match(self::HOST, $host) !== 1) {
            return null;
        }

        $port = $this->port($this->config->get($connection.'.public.port'))
            ?? (isset($site['port']) && ! $this->string($connection.'.public.host') ? (int) $site['port'] : null)
            ?? ($scheme === 'https' ? 443 : 80);

        if ($port < 1 || $port > 65535) {
            return null;
        }

        return new RealtimeClientConfigDTO(key: $key, host: $host, port: $port, tls: $scheme === 'https');
    }

    public function connectSource(): ?string
    {
        return $this->client()?->origin();
    }

    public function isIncomplete(): bool
    {
        return $this->usesReverb() && ! $this->enabled();
    }

    private function usesReverb(): bool
    {
        return $this->config->get('broadcasting.default') === self::CONNECTION;
    }

    private function string(string $key): ?string
    {
        $value = $this->config->get($key);

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * A port given as a number or digits; anything else (unset, blank) means the default, and a
     * number out of range is kept so the caller refuses it.
     */
    private function port(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d{1,6}$/D', trim($value)) === 1) {
            return (int) trim($value);
        }

        return null;
    }
}
