<?php

declare(strict_types=1);

namespace App\Infrastructure\Proposals\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Proposals\DTOs\ProposalEligibilityDTO;
use App\Application\Proposals\Services\ProposalEligibilityServiceInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Domain\Proposals\Enums\ProposalAccessLevel;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final readonly class ProposalEligibilityService implements ProposalEligibilityServiceInterface
{
    public function __construct(
        private ProposalSettingsServiceInterface $settings,
        private AuthorizationServiceInterface $authorization,
    ) {}

    public function check(ProposalType $type, ?string $userId): ProposalEligibilityDTO
    {
        try {
            return $this->evaluate($type, $userId);
        } catch (Throwable $e) {
            Log::error('[Proposals] Could not check who may propose; nobody may until it works.', [
                'type' => $type->value,
                'exception' => $e,
            ]);

            return ProposalEligibilityDTO::denied(ProposalEligibilityDTO::DISABLED);
        }
    }

    private function evaluate(ProposalType $type, ?string $userId): ProposalEligibilityDTO
    {
        $settings = $this->settings->settings()->forType($type);

        if (! $settings->enabled) {
            return ProposalEligibilityDTO::denied(ProposalEligibilityDTO::DISABLED);
        }

        // Deactivated accounts are read too, to be told apart from guests
        $user = $userId !== null && Str::isUuid($userId)
            ? UserModel::query()->withTrashed()->find($userId)
            : null;

        if (! $user instanceof UserModel) {
            return ProposalEligibilityDTO::denied(ProposalEligibilityDTO::AUTHENTICATION_REQUIRED);
        }

        if ($user->trashed() || $user->anonymized_at !== null) {
            return ProposalEligibilityDTO::denied(ProposalEligibilityDTO::ACCOUNT_INACTIVE);
        }

        // Always, not through the site's verification setting nor Laravel's `verified` middleware
        if (! $user->hasVerifiedEmail()) {
            return ProposalEligibilityDTO::denied(ProposalEligibilityDTO::EMAIL_UNVERIFIED);
        }

        return match ($settings->accessLevel) {
            null => ProposalEligibilityDTO::denied(ProposalEligibilityDTO::NO_ACCESS_LEVEL),
            ProposalAccessLevel::Registered => ProposalEligibilityDTO::allowed(),
            ProposalAccessLevel::Roles => match (true) {
                $settings->roles === [] => ProposalEligibilityDTO::denied(ProposalEligibilityDTO::NO_ROLES_CONFIGURED),
                $this->authorization->hasAnyRole($user, $settings->roles) => ProposalEligibilityDTO::allowed(),
                default => ProposalEligibilityDTO::denied(ProposalEligibilityDTO::ROLE_NOT_ALLOWED),
            },
            ProposalAccessLevel::Permission => $this->authorization->can($user, $type->proposePermission())
                ? ProposalEligibilityDTO::allowed()
                : ProposalEligibilityDTO::denied(ProposalEligibilityDTO::PERMISSION_DENIED),
        };
    }
}
