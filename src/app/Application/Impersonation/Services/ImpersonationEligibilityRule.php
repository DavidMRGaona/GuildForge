<?php

declare(strict_types=1);

namespace App\Application\Impersonation\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Enums\UserRole;
use App\Domain\Impersonation\Enums\ImpersonationRefusal;
use Illuminate\Contracts\Auth\Authenticatable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The one place that decides who may impersonate whom, used when starting and again on every
 * request of an impersonation. The actor needs users.impersonate, which only the administrator
 * role can hold (AdminOnlyPermissionRule). The target may be any account except an
 * administrator, the actor's own, a deactivated or an anonymized one; accounts with panel
 * access included.
 *
 * It fails closed: a check that throws refuses with CheckFailed.
 *
 * A class of its own rather than a method of a core interface, like AdminOnlyPermissionRule:
 * modules implement those interfaces in their tests, and a new method would break them.
 */
final readonly class ImpersonationEligibilityRule
{
    public const string PERMISSION = 'users.impersonate';

    public function __construct(
        private AuthorizationServiceInterface $authorization,
        private ImpersonationAccountsInterface $accounts,
        private ImpersonationContextInterface $context,
        private LoggerInterface $log,
    ) {}

    /**
     * Whether the actor may impersonate anybody right now (for offering the action).
     */
    public function mayImpersonate(Authenticatable $actor): bool
    {
        return ! $this->guarded(fn (): ?ImpersonationRefusal => $this->context->isActive()
            ? ImpersonationRefusal::AlreadyImpersonating
            : $this->actorRefusal($actor)) instanceof ImpersonationRefusal;
    }

    public function mayStart(Authenticatable $actor, Authenticatable $target): bool
    {
        return ! $this->startRefusal($actor, $target) instanceof ImpersonationRefusal;
    }

    /**
     * Why the actor may not start impersonating the target now; null when they may.
     */
    public function startRefusal(Authenticatable $actor, Authenticatable $target): ?ImpersonationRefusal
    {
        return $this->guarded(fn (): ?ImpersonationRefusal => $this->context->isActive()
            ? ImpersonationRefusal::AlreadyImpersonating
            : $this->actorRefusal($actor) ?? $this->targetRefusal($actor, $target));
    }

    /**
     * Why an impersonation under way must end; null while it may go on. Checked on every
     * request before the request runs as the target.
     */
    public function continuationRefusal(Authenticatable $actor, Authenticatable $target): ?ImpersonationRefusal
    {
        return $this->guarded(fn (): ?ImpersonationRefusal => $this->actorRefusal($actor) ?? $this->targetRefusal($actor, $target));
    }

    private function actorRefusal(Authenticatable $actor): ?ImpersonationRefusal
    {
        return $this->authorization->can($actor, self::PERMISSION) ? null : ImpersonationRefusal::NotPermitted;
    }

    private function targetRefusal(Authenticatable $actor, Authenticatable $target): ?ImpersonationRefusal
    {
        return match (true) {
            (string) $actor->getAuthIdentifier() === (string) $target->getAuthIdentifier() => ImpersonationRefusal::TargetIsSelf,
            $this->accounts->isDeactivated($target) => ImpersonationRefusal::TargetDeactivated,
            $this->accounts->isAnonymized($target) => ImpersonationRefusal::TargetAnonymized,
            $this->authorization->hasRole($target, UserRole::Admin->value) => ImpersonationRefusal::TargetIsAdmin,
            default => null,
        };
    }

    /**
     * @param  callable(): ?ImpersonationRefusal  $check
     */
    private function guarded(callable $check): ?ImpersonationRefusal
    {
        try {
            return $check();
        } catch (Throwable $exception) {
            $this->log->warning('Impersonation check failed; refused.', ['exception' => $exception::class]);

            return ImpersonationRefusal::CheckFailed;
        }
    }
}
