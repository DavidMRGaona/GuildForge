<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Exceptions\UserNotFoundException;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ValueObjects\UserId;

final readonly class UserService implements UserServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AuthorizationServiceInterface $authService,
        private SettingsServiceInterface $settingsService,
    ) {
    }

    public function canAccessPanel(string $userId): bool
    {
        $userModel = $this->userRepository->findModelById(UserId::fromString($userId));

        if ($userModel === null) {
            return false;
        }

        if ($this->authService->can($userModel, 'admin.access')) {
            return true;
        }

        return $userModel->role?->canAccessPanel() ?? false;
    }

    public function anonymize(string $userId): void
    {
        $userModel = $this->userRepository->findModelById(UserId::fromString($userId));

        if ($userModel === null) {
            throw UserNotFoundException::withId($userId);
        }

        $anonymousName = $this->settingsService->get('anonymized_user_name', 'Anónimo');

        $userModel->anonymize($anonymousName);
    }

    public function isAdmin(string $userId): bool
    {
        $userModel = $this->userRepository->findModelById(UserId::fromString($userId));

        if ($userModel === null) {
            return false;
        }

        return $this->authService->hasRole($userModel, 'admin');
    }
}
