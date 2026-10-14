<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Domain\Authorization\Exceptions\RoleNotFoundException;
use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class MemberRoleService implements MemberRoleServiceInterface
{
    public function __construct(
        private AuthorizationServiceInterface $authorization,
    ) {}

    public function assignTo(object $user): void
    {
        if (! RoleModel::query()->where('name', UserRole::Member->value)->exists()) {
            /** @var UserModel $user */
            Log::warning('The member role does not exist; user '.$user->getKey().' was created without it. Create the role and run users:assign-member-role');

            return;
        }

        $this->authorization->assignRole($user, UserRole::Member->value);
    }

    public function assignToAllUsers(bool $dryRun = false, int $chunkSize = 500): int
    {
        $memberId = RoleModel::query()->where('name', UserRole::Member->value)->value('id');

        if (! is_string($memberId)) {
            throw RoleNotFoundException::withName(UserRole::Member->value);
        }

        // Soft-deleted accounts as well: a restored account must not come back without its base role
        $withoutMember = UserModel::withTrashed()
            ->whereDoesntHave('roles', static fn (Builder $roles): Builder => $roles->whereKey($memberId));

        if ($dryRun) {
            return $withoutMember->count();
        }

        $assigned = 0;

        $withoutMember->select('users.id')->chunkById(
            $chunkSize,
            static function (Collection $users) use ($memberId, &$assigned): void {
                $now = now();

                // Direct inserts instead of assignRole(): one cache invalidation for the whole run
                $assigned += DB::table('user_role')->insertOrIgnore($users->map(
                    static fn (UserModel $user): array => [
                        'user_id' => $user->id,
                        'role_id' => $memberId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                )->all());
            },
            'users.id',
            'id',
        );

        if ($assigned > 0) {
            $this->authorization->invalidateCache();
        }

        return $assigned;
    }
}
