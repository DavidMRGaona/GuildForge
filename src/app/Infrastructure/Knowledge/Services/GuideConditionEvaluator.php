<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Services;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Knowledge\Services\GuideConditionEvaluatorInterface;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Domain\Knowledge\ValueObjects\GuideConditions;

/**
 * Bound `scoped`: capability results are memoized per user for the request or job.
 */
final class GuideConditionEvaluator implements GuideConditionEvaluatorInterface
{
    /** @var array<string, ?bool> */
    private array $capabilityResults = [];

    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly AssociationMembershipServiceInterface $membership,
        private readonly KnowledgeCapabilityRegistryInterface $capabilities,
        private readonly EnabledModulesResolverInterface $modules,
    ) {}

    public function passes(object $user, GuideConditions $conditions, ?string $module): bool
    {
        $enabled = $this->modules->names();

        if ($module !== null && ! in_array($module, $enabled, true)) {
            return false;
        }

        foreach ($conditions->modules as $required) {
            if (! in_array($required, $enabled, true)) {
                return false;
            }
        }

        if ($conditions->permissionsAll !== [] && ! $this->authorization->canAll($user, $conditions->permissionsAll)) {
            return false;
        }

        if ($conditions->permissionsAny !== [] && ! $this->authorization->canAny($user, $conditions->permissionsAny)) {
            return false;
        }

        if ($conditions->associationMember !== null
            && $this->membership->isAssociationMember($user) !== $conditions->associationMember) {
            return false;
        }

        foreach ($conditions->capabilities as $key) {
            if ($this->capability($user, $key) !== true) {
                return false;
            }
        }

        return array_all($conditions->negatedCapabilities, fn ($key) => $this->capability($user, $key) === false);
    }

    private function capability(object $user, string $key): ?bool
    {
        $id = $user->id ?? null;

        if (! is_string($id) && ! is_int($id)) {
            return $this->capabilities->evaluate($key, $user);
        }

        $memo = $id.'|'.$key;
        if (! array_key_exists($memo, $this->capabilityResults)) {
            $this->capabilityResults[$memo] = $this->capabilities->evaluate($key, $user);
        }

        return $this->capabilityResults[$memo];
    }
}
