<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Impersonation\Services\ImpersonationAccountsInterface;
use App\Application\Impersonation\Services\ImpersonationEligibilityRule;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationRefusal;
use App\Infrastructure\Impersonation\ImpersonationContext;
use App\Infrastructure\Impersonation\ImpersonationSessionState;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Runs the request as the impersonated account while the administrator's session holds an
 * impersonation. In the web group (site pages and every Livewire request) and in the panel,
 * after the session starts and before anything checks access.
 *
 * Every request checks again with ImpersonationEligibilityRule, from the account the session
 * belongs to. When the check refuses, the state is incomplete or anything throws, it fails
 * closed: the state is dropped, the log entry closed as revoked, and the request runs as the
 * administrator. Signing out ends it first (logout), so the guard signs the administrator out
 * and never touches the other account's «remember me».
 *
 * The guard holds the target only during the request: it goes back to the administrator
 * once the response is built, unless the request changed it (signed out, stopped).
 */
final readonly class ApplyImpersonation
{
    /** Request attribute: applied once per request even when both stacks include it */
    public const string APPLIED = 'impersonation.applied';

    /** @var list<string> */
    public const array LOGOUT_ROUTES = ['logout', 'filament.*.auth.logout'];

    public function __construct(
        private ImpersonationContext $context,
        private ImpersonationServiceInterface $impersonation,
        private ImpersonationEligibilityRule $rule,
        private ImpersonationAccountsInterface $accounts,
        private AuthFactory $auth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->has(self::APPLIED)) {
            return $next($request);
        }

        $request->attributes->set(self::APPLIED, true);
        $this->context->clear();

        if (! $request->hasSession() || ! $request->session()->has(ImpersonationSessionState::SESSION_KEY)) {
            return $next($request);
        }

        $guard = $this->auth->guard();
        $actor = $guard->user();

        try {
            $target = $this->resolveTarget($request, $actor);
        } catch (Throwable $exception) {
            $this->revokeAfterFailure($request, $exception);

            return $next($request);
        }

        if (! $target instanceof Authenticatable || $actor === null) {
            return $next($request);
        }

        $guard->setUser($target);

        try {
            return $next($request);
        } finally {
            if ($guard->user() === $target) {
                $guard->setUser($actor);
            }
        }
    }

    /**
     * The account the request runs as; null when it runs as whoever the session belongs to.
     */
    private function resolveTarget(Request $request, ?Authenticatable $actor): ?Authenticatable
    {
        if ($request->routeIs(...self::LOGOUT_ROUTES)) {
            $this->impersonation->stop(ImpersonationEndReason::Logout);

            return null;
        }

        $state = ImpersonationSessionState::fromSession($request->session()->get(ImpersonationSessionState::SESSION_KEY));
        $target = $state instanceof ImpersonationSessionState ? $this->accounts->find($state->targetId) : null;

        if (! $state instanceof ImpersonationSessionState
            || ! $target instanceof Authenticatable
            || ! $actor instanceof Authenticatable
            || (string) $actor->getAuthIdentifier() !== $state->impersonatorId
            || $this->rule->continuationRefusal($actor, $target) instanceof ImpersonationRefusal) {
            $this->impersonation->stop(ImpersonationEndReason::Revoked);

            return null;
        }

        $this->context->activate($actor, $target, $state->mode, $state->logId);

        return $target;
    }

    private function revokeAfterFailure(Request $request, Throwable $exception): void
    {
        Log::warning('Impersonation check failed; back to the account signed in.', ['exception' => $exception::class]);

        try {
            $this->impersonation->stop(ImpersonationEndReason::Revoked);
        } catch (Throwable) {
            // stop() drops the state even when the entry cannot be closed; make sure of it
            $request->session()->forget(ImpersonationSessionState::SESSION_KEY);
            $this->context->clear();
        }
    }
}
