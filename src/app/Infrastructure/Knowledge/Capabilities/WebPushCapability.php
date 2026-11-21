<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Capabilities;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use App\Application\WebPush\Services\WebPushConfigurationInterface;

/**
 * Passes while browser push notifications are configured on this site (core#804). Without them
 * the «Avisos» tab offers no push, so its guide is hidden too.
 */
final readonly class WebPushCapability implements KnowledgeCapabilityInterface
{
    public function __construct(
        private WebPushConfigurationInterface $configuration,
    ) {}

    public function key(): string
    {
        return 'core.web_push';
    }

    public function passes(object $user): bool
    {
        return $this->configuration->enabled();
    }
}
