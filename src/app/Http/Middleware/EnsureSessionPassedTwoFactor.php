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
 * Signs out a session that does not hold its account's current seal (TwoFactorSessionSealInterface):
 * one opened before two-factor authentication was turned on, off or reset, or one of an account
 * with it on that skipped the challenge. An account that never had a seal is left alone.
 *
 * In the web group and the panel's middleware. Not inside the AuthenticateSession subclasses: the
 * web group does not run AuthenticateSession, and adding it there would also start signing out
 * site sessions on every password change.
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

        if (! $this->seal->passes($session, $owner)) {
            $guard = $this->auth->guard();

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
