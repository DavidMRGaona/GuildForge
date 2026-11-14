<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Impersonation\DTOs\ViewedRoleDTO;
use App\Application\Impersonation\Services\ImpersonationAccountsInterface;
use App\Application\Impersonation\Services\ImpersonationEligibilityRule;
use App\Application\Impersonation\Services\ImpersonationRolesInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationRefusal;
use App\Infrastructure\Impersonation\ImpersonationContext;
use App\Infrastructure\Impersonation\ImpersonationSessionState;
use App\Infrastructure\Impersonation\RoleViewAccount;
use App\Infrastructure\Impersonation\SafetyNet\ImpersonationSafetyNet;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
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
 *
 * While it runs as the target, nothing persists (ImpersonationSafetyNet): the rest of the
 * request runs in a transaction that is always rolled back, inside this middleware so the
 * log entries written here and the session (saved by StartSession, outside) are kept. Ending
 * it («Volver a mi cuenta», signing out) runs outside: it has to close the entry.
 *
 * A role view has no account outside that transaction: it is checked from its roles, and the
 * account it runs as is built inside the transaction on every request (RoleViewAccount), so
 * it is gone with everything else once the response is built.
 */
final readonly class ApplyImpersonation
{
    /** Request attribute: applied once per request even when both stacks include it */
    public const string APPLIED = 'impersonation.applied';

    /** @var list<string> */
    public const array LOGOUT_ROUTES = ['logout', 'filament.*.auth.logout'];

    /**
     * Flash key for notices meant for the administrator (core#614). The site's band shows it on
     * any page, which plain `error` flashes do not reach.
     */
    public const string NOTICE_FLASH = 'impersonation_notice';

    /** JSON answer when a role view ends because its account could not be built */
    public const string ENDED_JSON_ERROR = 'impersonation_ended';

    /** «Volver a mi cuenta»: it closes the log entry, so it runs outside the rolled back transaction */
    public const string STOP_ROUTE = 'impersonation.stop';

    public function __construct(
        private ImpersonationContext $context,
        private ImpersonationServiceInterface $impersonation,
        private ImpersonationEligibilityRule $rule,
        private ImpersonationAccountsInterface $accounts,
        private AuthFactory $auth,
        private ImpersonationSafetyNet $safetyNet,
        private ImpersonationRolesInterface $roles,
        private RoleViewAccount $roleViewAccount,
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

        if (is_array($target) && $actor instanceof Authenticatable) {
            return $this->runRoleView($request, $next, $actor, $target['state'], $target['roles']);
        }

        if (! $target instanceof Authenticatable || $actor === null) {
            return $next($request);
        }

        $guard->setUser($target);

        try {
            if ($request->routeIs(self::STOP_ROUTE)) {
                return $next($request);
            }

            return $this->safetyNet->run(static fn (): Response => $next($request));
        } finally {
            if ($guard->user() === $target) {
                $guard->setUser($actor);
            }
        }
    }

    /**
     * The account the request runs as, or the state and roles of a role view, whose account can
     * only be built inside the rolled back transaction; null when it runs as whoever the session
     * belongs to.
     *
     * @return Authenticatable|array{state: ImpersonationSessionState, roles: non-empty-list<ViewedRoleDTO>}|null
     */
    private function resolveTarget(Request $request, ?Authenticatable $actor): Authenticatable|array|null
    {
        if ($request->routeIs(...self::LOGOUT_ROUTES)) {
            $this->impersonation->stop(ImpersonationEndReason::Logout);

            return null;
        }

        $state = ImpersonationSessionState::fromSession($request->session()->get(ImpersonationSessionState::SESSION_KEY));

        if ($state instanceof ImpersonationSessionState && $state->isRoleView()) {
            return $this->resolveRoleView($state, $actor);
        }

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

    /**
     * Checked from the roles: the view's account does not exist out here, so accounts->find()
     * and continuationRefusal() would always revoke it. The roles are read here too, outside
     * the transaction, so a failure revokes before the safety net engages, as for an account.
     *
     * @return array{state: ImpersonationSessionState, roles: non-empty-list<ViewedRoleDTO>}|null
     */
    private function resolveRoleView(ImpersonationSessionState $state, ?Authenticatable $actor): ?array
    {
        if (! $actor instanceof Authenticatable
            || (string) $actor->getAuthIdentifier() !== $state->impersonatorId
            || $this->rule->roleViewContinuationRefusal($actor, $state->roleIds ?? []) instanceof ImpersonationRefusal) {
            $this->impersonation->stop(ImpersonationEndReason::Revoked);

            return null;
        }

        return ['state' => $state, 'roles' => $this->viewedRoles($state)];
    }

    /**
     * @param  non-empty-list<ViewedRoleDTO>  $roles
     */
    private function runRoleView(Request $request, Closure $next, Authenticatable $actor, ImpersonationSessionState $state, array $roles): Response
    {
        // Outside the transaction there is no account: «Volver a mi cuenta» runs as the
        // administrator and stop() closes the entry from the state
        if ($request->routeIs(self::STOP_ROUTE)) {
            return $next($request);
        }

        $guard = $this->auth->guard();

        $result = $this->safetyNet->run(function () use ($request, $next, $actor, $state, $roles, $guard): Response|Throwable {
            try {
                $account = $this->roleViewAccount->create($roles);
            } catch (Throwable $exception) {
                // Handed out rather than revoked here: closing the entry inside the transaction
                // would be rolled back with the rest
                return $exception;
            }

            $names = array_map(static fn (ViewedRoleDTO $role): string => $role->displayName, $roles);
            $this->context->activate($actor, $account, $state->mode, $state->logId, $names);
            $guard->setUser($account);

            try {
                return $next($request);
            } finally {
                if ($guard->user() === $account) {
                    $guard->setUser($actor);
                }
            }
        });

        if ($result instanceof Throwable) {
            $this->revokeAfterFailure($request, $result);

            return $this->answerWithoutRunning($request);
        }

        return $result;
    }

    /**
     * The safety net is engaged by now for the rest of the process: the administrator's own
     * request never runs under it, so the browser asks again and that request runs clean.
     */
    private function answerWithoutRunning(Request $request): Response
    {
        // A Livewire call follows a redirect by XHR, so it gets the error like JSON does
        if ($request->hasHeader('X-Livewire') || ($request->expectsJson() && ! $request->hasHeader('X-Inertia'))) {
            return response()->json(['error' => self::ENDED_JSON_ERROR], Response::HTTP_CONFLICT);
        }

        // 303 so the browser asks again with a GET: HandleInertiaRequests runs inside this
        // middleware, so Inertia's own 302 to 303 rewrite never sees this answer
        if ($request->isMethodSafe()) {
            return redirect()->to($request->fullUrl(), Response::HTTP_SEE_OTHER);
        }

        return redirect()->back(Response::HTTP_SEE_OTHER);
    }

    /**
     * Every role of the view, or none: an account with fewer roles than the view would be
     * another view.
     *
     * @return non-empty-list<ViewedRoleDTO>
     */
    private function viewedRoles(ImpersonationSessionState $state): array
    {
        $ids = array_values(array_unique($state->roleIds ?? []));
        $roles = $this->roles->find($ids);

        if ($roles === [] || count($roles) !== count($ids)) {
            throw new RuntimeException('A role of the view is no longer available.');
        }

        return $roles;
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
