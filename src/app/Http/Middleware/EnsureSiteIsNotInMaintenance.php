<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the public site while maintenance mode is enabled in the site settings.
 *
 * Unlike `artisan down`, this keeps the admin panel, the health check and the
 * queue worker running, so module updates can still be applied.
 */
final readonly class EnsureSiteIsNotInMaintenance
{
    private const int RETRY_AFTER_SECONDS = 600;

    /**
     * The public login stays open so staff can sign in and get past the maintenance page.
     *
     * @var array<string>
     */
    private const array EXCLUDED_PATHS = [
        'admin',
        'admin/*',
        'livewire/*',
        'up',
        'iniciar-sesion',
    ];

    public function __construct(
        private SettingsServiceInterface $settings,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->isMaintenanceModeEnabled()
            || $request->is(...self::EXCLUDED_PATHS)
            || $this->canAccessPanel($request)) {
            return $next($request);
        }

        $response = Inertia::render('Maintenance', [
            'message' => $this->settings->getMaintenanceMessage(),
        ])->toResponse($request);

        $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
        $response->headers->set('Retry-After', (string) self::RETRY_AFTER_SECONDS);

        return $response;
    }

    private function canAccessPanel(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof UserModel && $user->canAccessPanel(Filament::getPanel('admin'));
    }
}
