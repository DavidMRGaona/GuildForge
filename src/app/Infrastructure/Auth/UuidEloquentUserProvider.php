<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

/**
 * Custom Eloquent User Provider that validates UUID format before querying.
 *
 * This prevents errors when old "remember me" cookies contain
 * integer IDs from before a UUID migration. Invalid IDs return null,
 * forcing the user to re-authenticate.
 */
class UuidEloquentUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        if (! $this->isValidUuid($identifier)) {
            return null;
        }

        return parent::retrieveById($identifier);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        if (! $this->isValidUuid($identifier)) {
            return null;
        }

        return parent::retrieveByToken($identifier, $token);
    }

    /**
     * Check if the given value is a valid UUID.
     */
    private function isValidUuid(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return Str::isUuid($value);
    }
}
