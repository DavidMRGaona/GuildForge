<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Services\EmailVerificationPolicyInterface;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the "email verification required" site setting on the server, for every route of
 * the `web` group (module routes included: ModuleServiceProvider::loadRoutes() puts them in it).
 *
 * Who must verify is decided by EmailVerificationPolicyInterface (setting on, email not verified,
 * account not anonymized and created from the enforcement cutoff on). Only routes that act as the
 * signed-in user are closed to those accounts, public pages stay open:
 * - routes with the `auth` middleware;
 * - routes with the `email.verified-if-required` alias (this class), for routes that work for
 *   guests but act as the signed-in user when there is one (e.g. signing up for a tournament).
 *   Without a signed-in user the alias does nothing.
 * Browser and Inertia visits are sent to `verification.notice` (remembering the page asked for);
 * JSON requests get a 403 with a translated `message` and `error` = JSON_ERROR_CODE.
 *
 * Always reachable:
 * - `verification.*`: the notice, resending the link and the verification links themselves;
 * - `logout`: an account must always be able to sign out;
 * - `profile.show` and `profile.update`: to correct a mistyped email (the change is confirmed
 *   through `verification.pending-email`, which also verifies the account);
 * - `filament.*`: the admin panel has its own middleware stack (not the `web` group) and access
 *   control; should it ever join the group, an unverified admin must not be locked out of it.
 * Livewire endpoints, `/up` and static assets carry no `auth`, so they are never stopped.
 */
final readonly class EnsureEmailIsVerifiedWhenRequired
{
    /**
     * Route middleware alias. On a `web` route the class then runs twice (group and route);
     * the second pass reaches the same decision, so it is harmless.
     */
    public const string ALIAS = 'email.verified-if-required';

    /**
     * Machine-readable code of the JSON 403 (`error` field), stable across locales unlike the
     * translated `message`; the frontend's useVerificationGate() recognises the response by it.
     */
    public const string JSON_ERROR_CODE = 'email_verification_required';

    /** @var array<string> */
    private const array EXCLUDED_ROUTE_NAMES = [
        'verification.*',
        'logout',
        'profile.show',
        'profile.update',
        'filament.*',
    ];

    public function __construct(
        private EmailVerificationPolicyInterface $policy,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || ! $this->actsAsTheUser($request)
            || $request->routeIs(...self::EXCLUDED_ROUTE_NAMES)
            || ! $this->policy->mustVerify($user)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            return response()->json(
                [
                    'message' => __('auth.email_verification_required'),
                    'error' => self::JSON_ERROR_CODE,
                ],
                Response::HTTP_FORBIDDEN,
            );
        }

        return redirect()->guest(route('verification.notice'));
    }

    private function actsAsTheUser(Request $request): bool
    {
        foreach ($request->route()?->gatherMiddleware() ?? [] as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            $name = explode(':', $middleware, 2)[0];

            // Subclasses count too: Filament's and any module's own Authenticate
            if (in_array($name, ['auth', self::ALIAS], true)
                || is_a($name, Authenticate::class, true)
                || is_a($name, self::class, true)) {
                return true;
            }
        }

        return false;
    }
}
