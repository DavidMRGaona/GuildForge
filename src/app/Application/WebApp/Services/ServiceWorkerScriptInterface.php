<?php

declare(strict_types=1);

namespace App\Application\WebApp\Services;

/**
 * The service worker served at `/sw.js` (core#802), with the settings it needs written before it.
 * Its URL never changes: the browser finds a new version by comparing the content.
 */
interface ServiceWorkerScriptInterface
{
    /**
     * The script, or null when the front end was never built (nothing to register).
     */
    public function script(): ?string;
}
