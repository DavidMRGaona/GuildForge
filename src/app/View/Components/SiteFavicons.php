<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Application\Services\SiteBrandingServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Throwable;

/**
 * Favicons of the first page load: the site's own when configured. Keeps the
 * selectors useFavicons.ts updates when the theme changes.
 */
final class SiteFavicons extends Component
{
    public function __construct(
        private readonly SiteBrandingServiceInterface $branding,
    ) {}

    public function render(): View
    {
        try {
            $custom = $this->branding->customFavicons()['light'];
        } catch (Throwable) {
            $custom = null;
        }

        return view('components.site-favicons', ['custom' => $custom]);
    }
}
