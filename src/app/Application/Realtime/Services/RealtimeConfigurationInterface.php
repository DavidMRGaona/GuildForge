<?php

declare(strict_types=1);

namespace App\Application\Realtime\Services;

use App\Application\Realtime\DTOs\RealtimeClientConfigDTO;

/**
 * Whether this installation broadcasts in real time, and where browsers connect. Real time is on
 * only when the broadcast connection is `reverb` and every setting it needs is present and valid;
 * anything else is off, and everything keeps working with periodic requests.
 */
interface RealtimeConfigurationInterface
{
    public function enabled(): bool;

    /**
     * What a browser needs to connect (public key, host, port, TLS), or null when off.
     */
    public function client(): ?RealtimeClientConfigDTO;

    /**
     * The WebSocket origin for the page's connect-src (`wss://host[:port]`), or null when off.
     */
    public function connectSource(): ?string;

    /**
     * The connection is `reverb` but a setting is missing or invalid, so real time stays off.
     */
    public function isIncomplete(): bool;
}
