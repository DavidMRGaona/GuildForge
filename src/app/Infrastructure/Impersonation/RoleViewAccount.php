<?php

declare(strict_types=1);

namespace App\Infrastructure\Impersonation;

use App\Application\Impersonation\DTOs\ViewedRoleDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The throwaway account a role view runs as. It only lives inside the impersonation safety net's
 * rolled back transaction. Written straight to the tables on purpose: no model events, observers,
 * role holder cap or role history for an account that never exists.
 */
final readonly class RoleViewAccount
{
    public const string EMAIL_DOMAIN = 'impersonation.invalid';

    /** Bcrypt hash of a random secret that was thrown away: nobody can sign in with it. */
    public const string PASSWORD_HASH = '$2y$12$iOpSY2EQ2o6WBi4eRmOS4OvrGQXmXZ7EzDsGtF48SdwaqFF777oVi';

    /**
     * @param  non-empty-list<ViewedRoleDTO>  $roles
     */
    public function create(array $roles): UserModel
    {
        $id = (string) Str::uuid7();
        $now = now();
        $name = implode(' + ', array_map(static fn (ViewedRoleDTO $role): string => $role->displayName, $roles));

        DB::table('users')->insert([
            'id' => $id,
            'name' => $name,
            'display_name' => $name,
            'email' => "{$id}@".self::EMAIL_DOMAIN,
            'password' => self::PASSWORD_HASH,
            'email_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_role')->insert(array_map(static fn (ViewedRoleDTO $role): array => [
            'user_id' => $id,
            'role_id' => $role->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $roles));

        return UserModel::query()->findOrFail($id);
    }
}
