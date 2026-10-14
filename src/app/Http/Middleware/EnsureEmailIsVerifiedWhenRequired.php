<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Services\EmailVerificationPolicyInterface;
use App\Http\Middleware\Concerns\IdentifiesLivewireRequests;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the "email verification required" site setting on the server, for every route of
 * the `web` group (module routes included: ModuleServiceProvider::loadRoutes() puts them in it).
 *
 * Who must verify is decided by EmailVerificationPolicyInterface (setting on, email not verified,
 * account not anonymized and created from the enforcement cutoff on). Only routes that require
 * login (`auth` middleware) are closed to those accounts: public pages stay open, as for guests.
 * Browser and Inertia visits are sent to `verification.notice` (remembering the page asked for);
 * JSON requests get a 403.
 *
 * Always reachable:
 * - `verification.*`: the notice, resending the link and the verification links themselves;
 * - `logout`: an account must always be able to sign out;
 * - `profile.show` and `profile.update`: to correct a mistyped email (the change is confirmed
 *   through `verification.pending-email`, which also verifies the account);
 * - the admin panel and Livewire endpoints: the panel has its own access control
 *   (canAccessPanel), and an unverified admin must not be locked out of it;
 * - `up`: the health check.
 * Static assets never reach PHP and other public routes have no `auth` middleware.
 */
final readonly class EnsureEmailIsVerifiedWhenRequired
{
    use IdentifiesLivewireRequests;

    /** @var array<string> */
    private const array EXCLUDED_ROUTE_NAMES = [
        'verification.*',
        'logout',
        'profile.show',
        'profile.update',
    ];

    /** @var array<string> */
    private const array EXCLUDED_PATHS = [
        'admin',
        'admin/*',
        'up',
    ];

    public function __construct(
        private EmailVerificationPolicyInterface $policy,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || ! $this->requiresLogin($request)
            || $request->routeIs(...self::EXCLUDED_ROUTE_NAMES)
            || $request->is(...self::EXCLUDED_PATHS)
            || $this->isLivewireRequest($request)
            || ! $this->policy->mustVerify($user)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            return response()->json(
                ['message' => __('auth.email_verification_required')],
                Response::HTTP_FORBIDDEN,
            );
        }

        return redirect()->guest(route('verification.notice'));
    }

    private function requiresLogin(Request $request): bool
    {
        foreach ($request->route()?->gatherMiddleware() ?? [] as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            $name = explode(':', $middleware, 2)[0];

            if ($name === 'auth' || $name === Authenticate::class) {
                return true;
            }
        }

        return false;
    }
}
