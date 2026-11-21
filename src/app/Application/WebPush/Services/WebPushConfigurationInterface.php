<?php

declare(strict_types=1);

namespace App\Application\WebPush\Services;

use App\Application\WebPush\DTOs\VapidKeysDTO;
use App\Domain\WebPush\WebPushEndpointRule;

/**
 * Whether this installation sends browser push notifications (core#803), as the real-time
 * configuration does for WebSockets: on only with every key set and valid. Off means no
 * subscription routes (404), no public key in the page and nothing sent.
 */
interface WebPushConfigurationInterface
{
    public function enabled(): bool;

    /** The VAPID public key browsers subscribe with; null while off. */
    public function publicKey(): ?string;

    /** The whole identity, to sign messages; null while off. */
    public function vapid(): ?VapidKeysDTO;

    /** Some key is set but the configuration is unusable: worth a warning where someone reads it. */
    public function isIncomplete(): bool;

    /** Which endpoints the site sends to: the browsers' push services and the configured extras. */
    public function endpointRule(): WebPushEndpointRule;
}
