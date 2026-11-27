<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Domain\Profiles\PublicName;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account without a public name chooses one, once, before acting as itself on the site
 * (core#855). The account name is never published, so the site cannot pick one for it: without a
 * public name the person is left out of profiles, recognitions and leaderboards.
 *
 * Like EnsureEmailIsVerifiedWhenRequired, it runs in the `web` group (module routes included) and
 * only on routes with `auth` (or a subclass of Authenticate); public pages stay open. It comes
 * after the two-factor check and the email verification: an account that must verify its email
 * is left to that middleware first, so it can still correct a mistyped address.
 * Browser and Inertia visits go to the screen (a page asked for with GET is remembered); JSON
 * requests get a 403 with a translated `message`, `error` = JSON_ERROR_CODE and the
 * JSON_ERROR_HEADER header.
 *
 * Never stops:
 * - an impersonation: whoever looks must not choose for the account;
 * - a request the maintenance mode lets through only for the two-factor setup;
 * - `public-name.*` (the screen), `logout`, `verification.*`, `profile.two-factor.*`,
 *   `impersonation.stop` and `filament.*` (the panel has its own stack; should it ever join the
 *   group, its users must not be locked out of it);
 * - a settings update that chooses the public name (forum#161): prompts on public pages, such as
 *   the forum's in place of its editor, save it with `profile.update` and keep the person on the
 *   page. Its form request still validates the name; an update without one (the avatar alone
 *   among them, which leaves the name as it is) is stopped like any other request.
 */
final readonly class EnsurePublicNameIsChosen
{
    /**
     * Machine-readable code of the JSON 403 (`error` field), stable across locales unlike the
     * translated `message`.
     */
    public const string JSON_ERROR_CODE = 'public_name_required';

    /**
     * Header set to `1` on the JSON 403, so a fetch() caller can recognise it from the Response
     * without reading the body.
     */
    public const string JSON_ERROR_HEADER = 'X-Public-Name-Required';

    /** @var array<string> */
    private const array EXCLUDED_ROUTE_NAMES = [
        'public-name.*',
        'logout',
        'verification.*',
        'profile.two-factor.*',
        'impersonation.stop',
        'filament.*',
    ];

    public function __construct(
        private ImpersonationContextInterface $impersonation,
        private EmailVerificationPolicyInterface $emailVerification,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof UserModel
            || ! PublicName::isMissing($user->display_name)
            || $user->anonymized_at !== null
            || ! $this->requiresLogin($request)
            || $request->routeIs(...self::EXCLUDED_ROUTE_NAMES)
            || $this->choosesPublicName($request)
            || $request->attributes->get(EnsureSiteIsNotInMaintenance::SECURITY_ONLY_ATTRIBUTE) === true
            || $this->impersonation->isActive()
            || $this->emailVerification->mustVerify($user)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            return response()->json(
                [
                    'message' => __('auth.public_name.required'),
                    'error' => self::JSON_ERROR_CODE,
                ],
                Response::HTTP_FORBIDDEN,
                [self::JSON_ERROR_HEADER => '1'],
            );
        }

        // Only a page is worth coming back to: a form sent from elsewhere would be opened with GET
        return $request->isMethod('GET')
            ? redirect()->guest(route('public-name.show'))
            : redirect()->route('public-name.show');
    }

    /**
     * Whether this is the settings update and it sets the public name: ProfileController::update()
     * writes it only together with the account name and email, so all three must come.
     */
    private function choosesPublicName(Request $request): bool
    {
        if (! $request->routeIs('profile.update')) {
            return false;
        }

        $publicName = $request->input('display_name');

        return is_string($request->input('name'))
            && is_string($request->input('email'))
            && is_string($publicName)
            && ! PublicName::isMissing($publicName);
    }

    private function requiresLogin(Request $request): bool
    {
        foreach ($request->route()?->gatherMiddleware() ?? [] as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            $name = explode(':', $middleware, 2)[0];

            // Subclasses count too: Filament's and any module's own Authenticate
            if ($name === 'auth' || is_a($name, Authenticate::class, true)) {
                return true;
            }
        }

        return false;
    }
}
