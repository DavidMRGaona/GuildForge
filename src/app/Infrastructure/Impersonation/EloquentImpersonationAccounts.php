<?php

declare(strict_types=1);

namespace App\Infrastructure\Impersonation;

use App\Application\Impersonation\Services\ImpersonationAccountsInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class EloquentImpersonationAccounts implements ImpersonationAccountsInterface
{
    public function find(string $id): ?Authenticatable
    {
        return UserModel::withTrashed()->find($id);
    }

    public function isDeactivated(Authenticatable $account): bool
    {
        return ! $account instanceof UserModel || $account->trashed();
    }

    public function isAnonymized(Authenticatable $account): bool
    {
        return ! $account instanceof UserModel || $account->anonymized_at !== null;
    }
}
