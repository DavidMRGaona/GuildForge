<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\DTOs\CreateUserDTO;
use App\Domain\Entities\User;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(UserId $id): ?User
    {
        $model = UserModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

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

    public function save(User $user): void
    {
        $existingModel = UserModel::query()->find($user->id()->value);

        if ($existingModel !== null) {
            // Update existing user - only update non-password fields
            $existingModel->update($this->toArray($user));
        } else {
            // Create new user - generate a random password
            $data = $this->toArray($user);
            $data['password'] = Hash::make(Str::random(32));
            UserModel::query()->create($data);
        }
    }

    public function create(CreateUserDTO $dto): User
    {
        $id = UserId::generate();

        $model = UserModel::query()->create([
            'id' => $id->value,
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password,
        ]);

        return $this->toDomain($model);
    }

    private function toDomain(UserModel $model): User
    {
        return new User(
            id: new UserId($model->id),
            name: $model->name,
            email: $model->email,
            displayName: $model->display_name,
            pendingEmail: $model->pending_email,
            avatarPublicId: $model->avatar_public_id,
            emailVerifiedAt: $model->email_verified_at !== null
                ? new DateTimeImmutable($model->email_verified_at->toDateTimeString())
                : null,
            anonymizedAt: $model->anonymized_at !== null
                ? new DateTimeImmutable($model->anonymized_at->toDateTimeString())
                : null,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            updatedAt: $model->updated_at !== null
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(User $user): array
    {
        return [
            'id' => $user->id()->value,
            'name' => $user->name(),
            'email' => $user->email(),
            'display_name' => $user->displayName(),
            'pending_email' => $user->pendingEmail(),
            'avatar_public_id' => $user->avatarPublicId(),
        ];
    }
}
