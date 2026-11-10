<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Application\Calendar\Services\CalendarFeedTokenServiceInterface;
use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\UserModelQueryServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Events\UserAnonymized;
use App\Domain\Exceptions\UserNotFoundException;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\SiteNotifications\Repositories\SiteNotificationPreferenceRepositoryInterface;
use App\Domain\SiteNotifications\Repositories\SiteNotificationRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class UserService implements UserServiceInterface
{
    public function __construct(
        private UserModelQueryServiceInterface $userModelQuery,
        private AuthorizationServiceInterface $authService,
        private SettingsServiceInterface $settingsService,
        private RoleAuditLoggerInterface $audit,
        private CalendarFeedTokenServiceInterface $calendarFeedTokens,
        private MailPreferenceRepositoryInterface $mailPreferences,
        private SiteNotificationRepositoryInterface $siteNotifications,
        private SiteNotificationPreferenceRepositoryInterface $siteNotificationPreferences,
    ) {}

    public function canAccessPanel(string $userId): bool
    {
        $userModel = $this->userModelQuery->findModelById(UserId::fromString($userId));

        if (! $userModel instanceof UserModel) {
            return false;
        }

        return $this->authService->can($userModel, 'admin.access');
    }

    public function anonymize(string $userId): void
    {
        $userModel = $this->userModelQuery->findModelByIdWithTrashed(UserId::fromString($userId));

        if (! $userModel instanceof UserModel) {
            throw UserNotFoundException::withId($userId);
        }

        $anonymousName = $this->settingsService->get('anonymized_user_name', 'Anónimo');

        DB::transaction(function () use ($userModel, $userId, $anonymousName): void {
            // Before the update: DeletesCloudinaryImages deletes the old avatar right away, so a
            // failed history write must stop the anonymization while the image still exists
            $this->removeEveryRole($userModel);
            // The personal calendar address would still tell what the person attends
            $this->calendarFeedTokens->revoke($userId);
            // So do the mail preferences
            $this->mailPreferences->deleteForUser(UserId::fromString($userId));
            // And the notification centre, with its choices (core#491)
            $this->forgetSiteNotifications(UserId::fromString($userId));

            $userModel->update([
                'name' => $anonymousName,
                'display_name' => null,
                'email' => 'anonymized_'.$userModel->id.'@anonymous.local',
                'pending_email' => null,
                'password' => Hash::make(Str::random(32)),
                'avatar_public_id' => null,
                'anonymized_at' => now(),
            ]);
        });

        $this->authService->invalidateCacheFor($userModel);
        $this->announceAnonymized((string) $userModel->id);
    }

    public function isAdmin(string $userId): bool
    {
        $userModel = $this->userModelQuery->findModelById(UserId::fromString($userId));

        if (! $userModel instanceof UserModel) {
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

        if (! $userModel instanceof UserModel) {
            throw UserNotFoundException::withId($dto->userId);
        }

        DB::transaction(function () use ($dto, $userModel): void {
            if ($dto->contentAction === 'transfer' && $dto->transferToUserId !== null) {
                $this->transferUserContent($dto->userId, $dto->transferToUserId);
            }

            $anonymousName = $this->settingsService->get('anonymized_user_name', 'Anónimo');

            // Before the update: DeletesCloudinaryImages deletes the old avatar right away, so a
            // failed history write must stop the anonymization while the image still exists
            $this->removeEveryRole($userModel);
            // The personal calendar address would still tell what the person attends
            $this->calendarFeedTokens->revoke($dto->userId);
            $this->mailPreferences->deleteForUser(UserId::fromString($dto->userId));
            $this->forgetSiteNotifications(UserId::fromString($dto->userId));

            $userModel->update([
                'name' => $anonymousName,
                'display_name' => null,
                'email' => 'anonymized_'.$userModel->id.'@anonymous.local',
                'pending_email' => null,
                'password' => Hash::make(Str::random(32)),
                'avatar_public_id' => null,
                'anonymized_at' => now(),
            ]);
        });

        $this->authService->invalidateCacheFor($userModel);
        $this->announceAnonymized((string) $userModel->id);
    }

    private function forgetSiteNotifications(UserId $userId): void
    {
        $this->siteNotifications->deleteForUser($userId);
        $this->siteNotificationPreferences->deleteForUser($userId);
    }

    /**
     * After the commit, and after the cache invalidation registered before it: modules that
     * mirror accounts elsewhere anonymize their copy at once.
     */
    private function announceAnonymized(string $userId): void
    {
        DB::afterCommit(static function () use ($userId): void {
            Event::dispatch(new UserAnonymized($userId));
        });
    }

    /**
     * Anonymizing takes every role away, the base role too: one history entry per role, in the
     * caller's transaction.
     */
    private function removeEveryRole(UserModel $userModel): void
    {
        /** @var list<string> $roleNames */
        $roleNames = $userModel->roles()->pluck('roles.name')->all();

        foreach ($roleNames as $roleName) {
            $this->audit->roleRemoved($userModel, $roleName);
        }

        $userModel->roles()->detach();
    }

    private function transferUserContent(string $fromUserId, string $toUserId): void
    {
        ArticleModel::where('author_id', $fromUserId)
            ->update(['author_id' => $toUserId]);
    }
}
