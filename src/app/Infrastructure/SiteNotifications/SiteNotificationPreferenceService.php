<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteNotifications;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\SiteNotifications\Services\SiteNotificationPreferenceServiceInterface;
use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;
use App\Domain\SiteNotifications\Repositories\SiteNotificationPreferenceRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Mail\Preferences\MailCategoryAudience;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final readonly class SiteNotificationPreferenceService implements SiteNotificationPreferenceServiceInterface
{
    public function __construct(
        private MailCategoryRegistryInterface $categories,
        private SiteNotificationPreferenceRepositoryInterface $preferences,
        private MailSubjectMuteServiceInterface $mutes,
    ) {}

    public function receives(object $user, MailCategoryDTO $category, ?MailSubjectDTO $subject = null): bool
    {
        if (! $category->site || ! $user instanceof UserModel) {
            return false;
        }

        if ($category->isMandatory()) {
            return true;
        }

        try {
            if (in_array($category->key, $this->preferences->disabledFor(UserId::fromString($user->id)), true)) {
                return false;
            }

            return ! $subject instanceof MailSubjectDTO || ! $this->mutes->isMuted($user, $subject);
        } catch (Throwable $exception) {
            // A notice too many in the bell is better than a missing one
            Log::warning('Could not read the site notification choices; the notice is shown.', [
                'category' => $category->key,
                'user_id' => $user->id,
                'exception' => $exception,
            ]);

            return true;
        }
    }

    public function update(object $user, array $changes): void
    {
        if (! $user instanceof UserModel) {
            throw new InvalidArgumentException('Only accounts have site notification preferences.');
        }

        foreach (array_keys($changes) as $key) {
            $key = (string) $key;
            $category = $this->categories->find($key);

            if (! $category instanceof MailCategoryDTO) {
                throw MailPreferenceNotEditableException::unknown($key);
            }

            if (! $category->site) {
                throw MailPreferenceNotEditableException::notOnSite($key);
            }

            if ($category->isMandatory()) {
                throw MailPreferenceNotEditableException::mandatory($key);
            }

            if (! MailCategoryAudience::includes($category, $user)) {
                throw MailPreferenceNotEditableException::notVisible($key);
            }
        }

        if ($changes !== []) {
            $this->preferences->save(UserId::fromString($user->id), $changes);
        }
    }
}
