<?php

declare(strict_types=1);

namespace App\Application\Services;

interface UserServiceInterface
{
    /**
     * Check if user can access the admin panel.
     */
    public function canAccessPanel(string $userId): bool;

    /**
     * Anonymize user data for GDPR compliance.
     * This action is irreversible.
     *
     * @throws \App\Domain\Exceptions\UserNotFoundException
     */
    public function anonymize(string $userId): void;

    /**
     * Check if user has admin role.
     */
    public function isAdmin(string $userId): bool;
}
