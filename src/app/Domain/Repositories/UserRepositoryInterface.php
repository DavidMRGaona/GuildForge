<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Application\DTOs\CreateUserDTO;
use App\Domain\Entities\User;
use App\Domain\ValueObjects\UserId;

interface UserRepositoryInterface
{
    public function findById(UserId $id): ?User;

    public function save(User $user): void;

    public function create(CreateUserDTO $dto): User;
}
