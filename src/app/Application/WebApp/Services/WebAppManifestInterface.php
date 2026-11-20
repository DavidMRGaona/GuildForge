<?php

declare(strict_types=1);

namespace App\Application\WebApp\Services;

use App\Application\WebApp\DTOs\WebAppManifestDTO;

/**
 * What makes the site installable (core#802): its manifest and the home screen icon of iOS.
 * Never throws: whatever cannot be read falls back to the core's name, colours and icons.
 */
interface WebAppManifestInterface
{
    public function manifest(): WebAppManifestDTO;

    /**
     * The 180 px icon iOS puts on the home screen (`apple-touch-icon`), on an opaque background:
     * iOS paints a transparent one black.
     */
    public function appleTouchIcon(): string;
}
