<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Http\Middleware\Concerns\IdentifiesLivewireRequests;
use App\Infrastructure\Authorization\Services\TwoFactorPanelAccess;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Closes the public site while maintenance mode is enabled in the site settings.
 *
 * Unlike `artisan down`, this keeps the admin panel, the health check and the
 * queue worker running, so module updates can still be applied.
 */
final readonly class EnsureSiteIsNotInMaintenance
{
    use IdentifiesLivewireRequests;

    private const int RETRY_AFTER_SECONDS = 600;

    /**
     * The public login stays open so staff can sign in and get past the maintenance page.
     * Livewire requests are let through separately: the admin login posts to them
     * before any session exists.
     *
     * @var array<string>
     */
    private const array EXCLUDED_PATHS = [
        'admin',
        'admin/*',
        'up',
        'iniciar-sesion',
        // Its second step, for accounts with two-factor authentication on (core#770)
        'iniciar-sesion/verificacion',
        // An administrator impersonating an account without panel access gets the maintenance
        // page like that account would, but can always go back to their own
        'impersonar/salir',
        // Unsubscribing from a mail must work whatever the site is doing
        'correos/baja/*',
        'correos/silenciar/*',
        // Mails sent meanwhile keep their logo
        'correos/logo.png',
    ];

    /** The account settings page, opened on its «Seguridad» tab only (core#772) */
    private const string SECURITY_PAGE_ROUTE = 'profile.show';

    private const string SECURITY_TAB = 'seguridad';

    /** Set on a request let through only for «Seguridad»; the account settings page reads it */
    public const string SECURITY_ONLY_ATTRIBUTE = 'maintenance_security_only';

    /** Turning two-factor authentication on (core#769) */
    private const string TWO_FACTOR_ROUTES = 'profile.two-factor.*';

    public function __construct(
        private SettingsServiceInterface $settings,
        private SeoMetadataFactoryInterface $seo,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->isMaintenanceModeEnabled()
            || $request->is(...self::EXCLUDED_PATHS)
            || $this->isLivewireRequest($request)
            || $this->canAccessPanel($request)) {
            return $next($request);
        }

        // Core#772: whoever would enter the panel with two-factor on may reach «Seguridad»
        if ($this->isTwoFactorSetupRoute($request) && $this->panelWaitsForTwoFactor($request)) {
            if ($request->routeIs(self::SECURITY_PAGE_ROUTE) && $request->query('tab') !== self::SECURITY_TAB) {
                return redirect()->route(self::SECURITY_PAGE_ROUTE, ['tab' => self::SECURITY_TAB]);
            }

            // The page then sends «Seguridad» alone: no other tab nor its data
            $request->attributes->set(self::SECURITY_ONLY_ATTRIBUTE, true);

            return $next($request);
        }

        // Routes without a session (feeds for calendar apps) are read by machines, and
        // the maintenance page needs the session for its shared props
        if (! $request->hasSession()) {
            return response(strip_tags($this->settings->getMaintenanceMessage() ?? ''), Response::HTTP_SERVICE_UNAVAILABLE, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Retry-After' => (string) self::RETRY_AFTER_SECONDS,
                'Cache-Control' => 'no-store',
            ]);
        }

        $response = Inertia::render('Maintenance', [
            'message' => $this->settings->getMaintenanceMessage(),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.maintenance'),
                noindex: true,
            ))->toArray(),
        ])->toResponse($request);

        $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
        $response->headers->set('Retry-After', (string) self::RETRY_AFTER_SECONDS);

        return $response;
    }

    private function isTwoFactorSetupRoute(Request $request): bool
    {
        return $request->routeIs(self::SECURITY_PAGE_ROUTE, self::TWO_FACTOR_ROUTES);
    }

    /**
     * Signed in, kept out of the panel only because roles that require two-factor authentication
     * are paused: without «Seguridad» there would be no way to turn it on until maintenance ends.
     */
    private function panelWaitsForTwoFactor(Request $request): bool
    {
        $user = $request->user();

        try {
            return $user instanceof UserModel && app(TwoFactorPanelAccess::class)->pausedOnlyByTwoFactor($user);
        } catch (Throwable) {
            // Fails closed: the maintenance page, as for anyone else
            return false;
        }
    }

    private function canAccessPanel(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof UserModel && $user->canAccessPanel(Filament::getPanel('admin'));
    }
}
