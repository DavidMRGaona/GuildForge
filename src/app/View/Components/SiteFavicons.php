<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\WebApp\Services\WebAppManifestInterface;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Throwable;

/**
 * Favicons of the first page load: the site's own when configured. Keeps the
 * selectors useFavicons.ts updates when the theme changes. The home screen icon of
 * iOS and the manifest come from the installable site (core#802) and do not follow the theme.
 */
final class SiteFavicons extends Component
{
    public function __construct(
        private readonly SiteBrandingServiceInterface $branding,
        private readonly WebAppManifestInterface $webApp,
    ) {}

    public function render(): View
    {
        try {
            $favicons = $this->branding->customFavicons();
            // The light theme loads first; a dark-only favicon is still the site's own
            $custom = $favicons['light'] ?? $favicons['dark'];
        } catch (Throwable) {
            $custom = null;
        }

        return view('components.site-favicons', [
            'custom' => $custom,
            'appleTouchIcon' => $this->webApp->appleTouchIcon(),
        ]);
    }
}
