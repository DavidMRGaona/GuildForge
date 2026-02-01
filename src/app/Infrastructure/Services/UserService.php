<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\UserModelQueryServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Exceptions\UserNotFoundException;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use Illuminate\Support\Facades\DB;

final readonly class UserService implements UserServiceInterface
{
    public function __construct(
        private UserModelQueryServiceInterface $userModelQuery,
        private AuthorizationServiceInterface $authService,
        private SettingsServiceInterface $settingsService,
    ) {
    }

    public function canAccessPanel(string $userId): bool
    {
        $userModel = $this->userModelQuery->findModelById(UserId::fromString($userId));

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
        $userModel = $this->userModelQuery->findModelByIdWithTrashed(UserId::fromString($userId));

        if ($userModel === null) {
            throw UserNotFoundException::withId($userId);
        }

        $anonymousName = $this->settingsService->get('anonymized_user_name', 'Anónimo');

        $userModel->anonymize($anonymousName);
    }

    public function isAdmin(string $userId): bool
    {
        $userModel = $this->userModelQuery->findModelById(UserId::fromString($userId));

        if ($userModel === null) {
            return false;
        }

        return $this->authService->hasRole($userModel, 'admin');
    }

    public function countUserContent(string $userId): array
    {
        return [
            'articles' => ArticleModel::where('author_id', $userId)->count(),
        ];
    }

    public function anonymizeWithContentTransfer(AnonymizeUserDTO $dto): void
    {
        $userModel = $this->userModelQuery->findModelByIdWithTrashed(UserId::fromString($dto->userId));

        if ($userModel === null) {
            throw UserNotFoundException::withId($dto->userId);
        }

        DB::transaction(function () use ($dto, $userModel): void {
            if ($dto->contentAction === 'transfer' && $dto->transferToUserId !== null) {
                $this->transferUserContent($dto->userId, $dto->transferToUserId);
            }

            $anonymousName = $this->settingsService->get('anonymized_user_name', 'Anónimo');
            $userModel->anonymize($anonymousName);
        });
    }

    private function transferUserContent(string $fromUserId, string $toUserId): void
    {
        ArticleModel::where('author_id', $fromUserId)
            ->update(['author_id' => $toUserId]);
    }
}
