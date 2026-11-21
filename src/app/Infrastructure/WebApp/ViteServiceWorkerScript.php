<?php

declare(strict_types=1);

namespace App\Infrastructure\WebApp;

use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\WebApp\Services\ServiceWorkerScriptInterface;
use App\Application\WebApp\Services\WebAppManifestInterface;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The service worker Vite builds from {@see self::ENTRY} under a hashed name, read from the build and
 * served at its fixed URL. A first line sets `self.serviceWorkerSettings` with what the worker cannot
 * know by itself: the site's texts (it has no i18n), its icon and where notices are listed. When those
 * change, the content changes and browsers install the new worker.
 */
final readonly class ViteServiceWorkerScript implements ServiceWorkerScriptInterface
{
    public const string ENTRY = 'resources/js/serviceWorker/sw.ts';

    /** JSON that can sit in a script without closing a tag or a string (`<`, `>`, `&`, quotes escaped). */
    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        | JSON_HEX_APOS | JSON_HEX_QUOT;

    public function __construct(
        private Vite $vite,
        private WebAppManifestInterface $manifest,
        private SiteBrandingServiceInterface $branding,
        private string $buildDirectory = 'build',
    ) {}

    public function script(): ?string
    {
        try {
            $source = $this->vite->content(self::ENTRY, $this->buildDirectory);
        } catch (Throwable) {
            // Not built (development without a build) or built without the worker: nothing to register
            return null;
        }

        if (trim($source) === '') {
            return null;
        }

        try {
            $settings = json_encode($this->settings(), self::JSON_FLAGS);
        } catch (Throwable $exception) {
            // A worker without its settings would show empty notifications: the route's quiet 404
            // instead, never a 500. The class only: a message may carry what was being read
            Log::warning('Service worker settings could not be built', ['exception' => $exception::class]);

            return null;
        }

        return 'self.serviceWorkerSettings = '.$settings.";\n".$source;
    }

    /**
     * @return array{fallbackTitle: string, fallbackBody: string, fallbackPath: string, icon: string}
     */
    private function settings(): array
    {
        return [
            'fallbackTitle' => __('web_app.service_worker.fallback_title'),
            'fallbackBody' => $this->siteName(),
            'fallbackPath' => route('site-notifications.index', absolute: false),
            'icon' => $this->manifest->notificationIcon(),
        ];
    }

    private function siteName(): string
    {
        try {
            return $this->branding->siteName();
        } catch (Throwable) {
            return (string) config('app.name');
        }
    }
}
