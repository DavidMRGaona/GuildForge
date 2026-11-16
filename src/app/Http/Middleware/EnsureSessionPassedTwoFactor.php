<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a session of an account with two-factor authentication on that does not hold the
 * account's seal (TwoFactorSessionSealInterface): one opened before it was turned on, or one that
 * skipped the challenge. In the web group and the panel's middleware.
 *
 * The seal is the session owner's: the administrator's while impersonating, like the password
 * hash AuthenticateSession compares (ChecksTheImpersonatorsSession).
 */
final readonly class EnsureSessionPassedTwoFactor
{
    public function __construct(
        private AuthFactory $auth,
        private ImpersonationContextInterface $impersonation,
        private TwoFactorSessionSealInterface $seal,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $owner = $this->impersonation->impersonator() ?? $request->user();

        if (! $owner instanceof Authenticatable || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $ownerId = (string) $owner->getAuthIdentifier();
        $guard = $this->auth->guard();

        // Restored from a «remember me» cookie, which is only issued after the challenge and was
        // invalidated for every older one when two-factor authentication was turned on
        if ($guard instanceof StatefulGuard && $guard->viaRemember()) {
            $this->seal->stamp($session, $ownerId);
        }

        if (! $this->seal->passes($session, $ownerId)) {
            // Only this device: logout() would also rotate the remember token of the account
            if ($guard instanceof SessionGuard) {
                $guard->logoutCurrentDevice();
            } elseif ($guard instanceof StatefulGuard) {
                $guard->logout();
            }

            $session->invalidate();
            $session->regenerateToken();

            throw new AuthenticationException('Unauthenticated.');
        }

        return $next($request);
    }
}
