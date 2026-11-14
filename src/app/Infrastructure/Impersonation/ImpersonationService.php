<?php

declare(strict_types=1);

namespace App\Infrastructure\Impersonation;

use App\Application\Impersonation\Services\ImpersonationEligibilityRule;
use App\Application\Impersonation\Services\ImpersonationLoggerInterface;
use App\Application\Impersonation\Services\ImpersonationRolesInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Impersonation\Enums\ImpersonationRefusal;
use App\Domain\Impersonation\Exceptions\ImpersonationNotAllowedException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Never Auth::login(): the session stays the administrator's, so no login event, no new
 * «remember me» and no password check are involved. Only the id changes (migrate), never the
 * CSRF token, so a panel page that ends it with a Livewire request keeps working.
 */
final readonly class ImpersonationService implements ImpersonationServiceInterface
{
    public function __construct(
        private ImpersonationEligibilityRule $rule,
        private ImpersonationLoggerInterface $logger,
        private ImpersonationRolesInterface $roles,
        private ImpersonationContext $context,
        private Session $session,
        private AuthFactory $auth,
        private Request $request,
    ) {}

    public function start(Authenticatable $actor, Authenticatable $target, ImpersonationMode $mode): void
    {
        if ($this->session->has(ImpersonationSessionState::SESSION_KEY)) {
            throw new ImpersonationNotAllowedException(ImpersonationRefusal::AlreadyImpersonating);
        }

        $refusal = $this->rule->startRefusal($actor, $target);
        if ($refusal instanceof ImpersonationRefusal) {
            throw new ImpersonationNotAllowedException($refusal);
        }

        $impersonatorId = (string) $actor->getAuthIdentifier();
        $targetId = (string) $target->getAuthIdentifier();
        $logId = $this->logger->started($impersonatorId, $targetId, $mode, $this->request->ip(), $this->request->userAgent());

        $state = new ImpersonationSessionState($targetId, $logId, $mode, $impersonatorId);
        $this->session->put(ImpersonationSessionState::SESSION_KEY, $state->toArray());
        $this->session->migrate(true);
    }

    public function startWithRoles(Authenticatable $actor, array $roleIds, ImpersonationMode $mode): void
    {
        if ($this->session->has(ImpersonationSessionState::SESSION_KEY)) {
            throw new ImpersonationNotAllowedException(ImpersonationRefusal::AlreadyImpersonating);
        }

        $baseRoleId = $this->roles->baseRoleId();
        if ($baseRoleId === null) {
            throw new ImpersonationNotAllowedException(ImpersonationRefusal::RoleUnavailable);
        }

        $ids = array_values(array_unique([...$roleIds, $baseRoleId]));

        $refusal = $this->rule->roleViewRefusal($actor, $ids);
        if ($refusal instanceof ImpersonationRefusal) {
            throw new ImpersonationNotAllowedException($refusal);
        }

        $impersonatorId = (string) $actor->getAuthIdentifier();
        // Belongs to no account: the request builds a throwaway one with this id
        $targetId = (string) Str::uuid7();
        $logId = $this->logger->started(
            $impersonatorId,
            $targetId,
            $mode,
            $this->request->ip(),
            $this->request->userAgent(),
            $this->roles->find($ids),
        );

        $state = new ImpersonationSessionState($targetId, $logId, $mode, $impersonatorId, $ids);
        $this->session->put(ImpersonationSessionState::SESSION_KEY, $state->toArray());
        $this->session->migrate(true);
    }

    public function stop(ImpersonationEndReason $reason): void
    {
        $stored = $this->session->get(ImpersonationSessionState::SESSION_KEY);
        $logId = $this->context->logId() ?? ImpersonationSessionState::logIdIn($stored);
        $impersonator = $this->context->impersonator();

        if ($stored === null && $logId === null) {
            return;
        }

        try {
            if ($logId !== null) {
                $this->logger->ended($logId, $reason);
            }
        } finally {
            $this->session->forget(ImpersonationSessionState::SESSION_KEY);
            $this->session->migrate(true);
            $this->context->clear();

            if ($impersonator instanceof Authenticatable) {
                $this->auth->guard()->setUser($impersonator);
            }
        }
    }
}
