<?php

declare(strict_types=1);

namespace App\Application\Services;

/**
 * The site's own branding as configured in the admin settings.
 *
 * Every method returns null/empty when the site uses the GuildForge defaults,
 * so callers can tell "custom" from "default" apart.
 */
interface SiteBrandingServiceInterface
{
    /** Storage path (Cloudinary public ID) of the light logo, or null. */
    public function customLogoPath(): ?string;

    /** Public URL of the light logo, or null. */
    public function customLogoUrl(): ?string;

    /**
     * @return array{light: string|null, dark: string|null}
     */
    public function customFavicons(): array;

    /**
     * @return list<string>
     */
    public function socialProfileUrls(): array;
}
