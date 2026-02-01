<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

/**
 * Service interface for retrieving Eloquent UserModel instances.
 *
 * This interface exists to provide Laravel-specific operations (notifications,
 * password resets, authentication) that require the Eloquent model directly.
 * It keeps the UserRepositoryInterface in Domain layer pure by moving
 * framework-coupled methods here in Application layer.
 *
 * Note: Application layer is allowed to import Infrastructure types
 * as it serves as the bridge between Domain and Infrastructure.
 */
interface UserModelQueryServiceInterface
{
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
}
