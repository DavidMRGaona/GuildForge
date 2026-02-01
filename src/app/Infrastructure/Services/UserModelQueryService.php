<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Services\UserModelQueryServiceInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

final readonly class UserModelQueryService implements UserModelQueryServiceInterface
{
    public function findModelById(UserId $id): ?UserModel
    {
        return UserModel::query()->find($id->value);
    }

    public function findModelByIdWithTrashed(UserId $id): ?UserModel
    {
        return UserModel::query()->withTrashed()->find($id->value);
    }

    public function findByEmail(string $email): ?UserModel
    {
        return UserModel::query()->where('email', $email)->first();
    }
}
