<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Application\Authorization\Services\RoleAuditLoggerInterface;
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
        private RoleAuditLoggerInterface $audit,
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

        // Soft-deleted accounts as well: a restored account must not come back without its base role.
        // Anonymized ones never: anonymizing removes every role on purpose.
        $withoutMember = UserModel::withTrashed()
            ->whereNull('users.anonymized_at')
            ->whereDoesntHave('roles', static fn (Builder $roles): Builder => $roles->whereKey($memberId));

        if ($dryRun) {
            return $withoutMember->count();
        }

        $assigned = 0;

        $withoutMember->select('users.id')->chunkById(
            $chunkSize,
            function (Collection $users) use ($memberId, &$assigned): void {
                /** @var list<string> $ids */
                $ids = array_values($users->pluck('id')->map(strval(...))->all());

                $assigned += DB::transaction(function () use ($ids, $memberId): int {
                    // Only the accounts still without it get an entry: one may have got it since the query
                    $holding = DB::table('user_role')->where('role_id', $memberId)->whereIn('user_id', $ids)->pluck('user_id')->map(strval(...))->all();
                    $missing = array_values(array_diff($ids, $holding));

                    if ($missing === []) {
                        return 0;
                    }

                    $now = now();

                    // Direct inserts instead of assignRole(): one cache invalidation for the whole run
                    $inserted = DB::table('user_role')->insertOrIgnore(array_map(
                        static fn (string $userId): array => [
                            'user_id' => $userId,
                            'role_id' => $memberId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        $missing,
                    ));

                    // One insert into the history per chunk, in the same transaction as the roles
                    $this->audit->roleAssignedToMany($missing, UserRole::Member->value);

                    return $inserted;
                });
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
