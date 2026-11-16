<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use Closure;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Signs out a session that does not hold its account's current seal (TwoFactorSessionSealInterface):
 * one opened before two-factor authentication was turned on, off or reset, or one of an account
 * with it on that skipped the challenge. An account that never had a seal is left alone.
 *
 * In the web group and the panel's middleware. Not inside the AuthenticateSession subclasses: the
 * web group does not run AuthenticateSession, and adding it there would also start signing out
 * site sessions on every password change.
 *
 * A page answers with a redirect to its own login (the panel's in the panel); a Livewire update
 * with Livewire's own redirect effect to it, rather than an error.
 *
 * Runs before ApplyImpersonation, so the seal checked is the session owner's (the administrator's
 * while impersonating, never the target's), and a session without it ends the impersonation
 * before the safety net engages, so its log entry is closed.
 */
final readonly class EnsureSessionPassedTwoFactor
{
    public function __construct(
        private AuthFactory $auth,
        private ImpersonationContextInterface $impersonation,
        private ImpersonationServiceInterface $impersonations,
        private TwoFactorSessionSealInterface $seal,
        private CookieJar $cookies,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $owner = $this->impersonation->impersonator() ?? $request->user();

        if (! $owner instanceof Authenticatable || ! $request->hasSession()) {
            return $next($request);
        }

        if ($this->seal->passes($request->session(), $owner)) {
            return $next($request);
        }

        $this->signOut($request);

        if ($request->hasHeader('X-Livewire')) {
            return $this->livewireRedirect($request) ?? $next($request);
        }

        throw new AuthenticationException('Unauthenticated.', [], $this->loginUrl($request->path()));
    }

    /**
     * A Livewire update answered with its own redirect effect for every component sent, so the
     * page goes to the login instead of showing an error. The snapshots go back unchanged. Null for
     * a payload of another shape: the update then goes on as a guest.
     */
    private function livewireRedirect(Request $request): ?JsonResponse
    {
        $components = $request->json('components');
        if (! is_array($components) || $components === []) {
            return null;
        }

        $answered = [];
        $path = null;
        foreach ($components as $component) {
            $snapshot = is_array($component) ? ($component['snapshot'] ?? null) : null;
            if (! is_string($snapshot)) {
                return null;
            }

            $decoded = json_decode($snapshot, true);
            $memoPath = is_array($decoded) && is_array($decoded['memo'] ?? null) ? ($decoded['memo']['path'] ?? null) : null;
            $path ??= is_string($memoPath) ? $memoPath : null;
            $answered[] = ['snapshot' => $snapshot, 'effects' => []];
        }

        $url = $this->loginUrl($path ?? '') ?? route('login');
        foreach ($answered as $index => $component) {
            $answered[$index]['effects'] = ['redirect' => $url];
        }

        return new JsonResponse(['components' => $answered, 'assets' => []]);
    }

    private function signOut(Request $request): void
    {
        // Reads the session's state, so it also ends one not applied yet; nothing without one
        try {
            $this->impersonations->stop(ImpersonationEndReason::Revoked);
        } catch (Throwable $exception) {
            // stop() drops the state even when the entry cannot be closed
            Log::warning('Impersonation could not be ended cleanly while signing out a session without its seal.', [
                'exception' => $exception::class,
            ]);
        }

        $guard = $this->auth->guard();

        // Only this device: logout() would also rotate the remember token of the account
        if ($guard instanceof SessionGuard) {
            $guard->logoutCurrentDevice();
        } elseif ($guard instanceof StatefulGuard) {
            $guard->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $this->cookies->queue($this->cookies->forget(TwoFactorSessionSeal::REMEMBER_COOKIE));
    }

    /**
     * The panel's own login for a panel page (the site's may be turned off); null for the site's.
     */
    private function loginUrl(string $path): ?string
    {
        try {
            $panel = Filament::getCurrentPanel() ?? $this->panelOf($path);
            $url = $panel?->getLoginUrl();
        } catch (Throwable) {
            $url = null;
        }

        return is_string($url) ? $url : null;
    }

    private function panelOf(string $path): ?Panel
    {
        $path = trim($path, '/');

        foreach (Filament::getPanels() as $panel) {
            $prefix = trim($panel->getPath(), '/');

            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
                return $panel;
            }
        }

        return null;
    }
}
