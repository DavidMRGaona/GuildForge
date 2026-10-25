<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Services\SettingsServiceInterface;

/**
 * The site's name: the association name set in the admin settings, else APP_NAME.
 *
 * A class of its own so that AboutPageService and SiteBrandingService (which
 * depends on AboutPageService) share the rule without a dependency cycle.
 * Callers outside them ask SiteBrandingServiceInterface::siteName().
 */
final readonly class SiteNameResolver
{
    public function __construct(
        private SettingsServiceInterface $settings,
    ) {}

    public function resolve(): string
    {
        $name = $this->settings->get('guild_name');

        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        return (string) config('app.name', 'GuildForge');
    }
}
