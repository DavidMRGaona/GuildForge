<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Application\DTOs\CreateUserDTO;
use App\Domain\Entities\User;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

interface UserRepositoryInterface
{
    public function findById(UserId $id): ?User;

    /**
     * Find user model by ID for Laravel-specific operations.
     * Returns the Eloquent model directly for notifications, etc.
     */
    public function findModelById(UserId $id): ?UserModel;

    /**
     * Find user model by ID including soft-deleted records.
     * Used for operations on deactivated users (anonymization, etc.).
     */
    public function findModelByIdWithTrashed(UserId $id): ?UserModel;

    /**
     * Find user model by email for authentication purposes.
     * Returns the Eloquent model directly for Laravel Auth compatibility.
     */
    public function findByEmail(string $email): ?UserModel;

    public function save(User $user): void;

    public function create(CreateUserDTO $dto): User;
}
