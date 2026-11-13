<?php

declare(strict_types=1);

namespace Tests\Support\Realtime;

/**
 * Turns real-time broadcasting on for a test, with example credentials and the browser's
 * WebSocket on the site's own host.
 */
trait EnablesRealtime
{
    public const string REALTIME_KEY = 'example-key';

    public const string REALTIME_SECRET = 'example-secret';

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function enableRealtime(array $overrides = []): void
    {
        config(array_merge([
            'app.url' => 'https://example.test',
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => self::REALTIME_KEY,
            'broadcasting.connections.reverb.secret' => self::REALTIME_SECRET,
            'broadcasting.connections.reverb.app_id' => 'example-app',
            'broadcasting.connections.reverb.options.host' => 'realtime.internal',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.public.host' => null,
            'broadcasting.connections.reverb.public.port' => null,
            'broadcasting.connections.reverb.public.scheme' => null,
        ], $overrides));
    }

    /**
     * The signature a browser must get for a private channel, as the Pusher protocol defines it.
     */
    protected function expectedRealtimeAuth(string $socketId, string $channel): string
    {
        return self::REALTIME_KEY.':'.hash_hmac('sha256', $socketId.':'.$channel, self::REALTIME_SECRET);
    }
}
