<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\AnonymizeUserDTO;

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
     * Anonymize user data with content transfer option.
     * Allows transferring user's content to another user before anonymization.
     *
     * @throws \App\Domain\Exceptions\UserNotFoundException
     */
    public function anonymizeWithContentTransfer(AnonymizeUserDTO $dto): void;

    /**
     * Count user's content (articles, etc.).
     *
     * @return array{articles: int}
     */
    public function countUserContent(string $userId): array;

    /**
     * Check if user has admin role.
     */
    public function isAdmin(string $userId): bool;
}
