<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Finds the accounts that hold every given permission, the way authorization sees them.
 *
 * The query only narrows the candidates (admins hold every permission without it being
 * listed on their role); the authorization service has the final word on each one, so
 * inactive roles and implicit admin access are honoured. A concrete class on purpose:
 * it is not part of AuthorizationServiceInterface, which modules implement in test doubles.
 */
final readonly class PermissionHolderFinder
{
    public function __construct(
        private AuthorizationServiceInterface $authorization,
    ) {}

    /**
     * @return Collection<int, UserModel>
     */
    public function usersWhoCan(string ...$permissions): Collection
    {
        if ($permissions === []) {
            throw new InvalidArgumentException('At least one permission is required.');
        }

        $keys = array_values(array_unique($permissions));

        return UserModel::query()
            ->whereNull('anonymized_at')
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where(
                fn (Builder $query): Builder => $query
                    ->where('name', UserRole::Admin->value)
                    ->orWhereHas('permissions', fn (Builder $perms): Builder => $perms->whereIn('key', $keys)),
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->filter(fn (UserModel $user): bool => $this->authorization->canAll($user, $keys))
            ->values();
    }
}
