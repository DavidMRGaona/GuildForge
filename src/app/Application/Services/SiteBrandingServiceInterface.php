<?php

declare(strict_types=1);

namespace App\Application\Services;

/**
 * The site's own branding as configured in the admin settings.
 *
 * The custom* methods return null/empty when the site uses the GuildForge
 * defaults, so callers can tell "custom" from "default" apart.
 */
interface SiteBrandingServiceInterface
{
    /** The site's name: the association name from the settings, else APP_NAME. Never empty. */
    public function siteName(): string;

    /** Storage path (Cloudinary public ID) of the site's logo: the light one, else the dark one, or null. */
    public function customLogoPath(): ?string;

    /** Public URL of the logo customLogoPath() returns, or null. */
    public function customLogoUrl(): ?string;

    /** Whether that logo is the dark-theme one (the site has no light logo). */
    public function customLogoIsDark(): bool;

    /**
     * @return array{light: string|null, dark: string|null}
     */
    public function customFavicons(): array;

    /**
     * @return list<string>
     */
    public function socialProfileUrls(): array;
}
