<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\WebPush\Services\WebPushPreferenceInterface;
use App\Application\WebPush\Services\WebPushPreferenceServiceInterface;
use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;
use App\Domain\ValueObjects\UserId;
use App\Domain\WebPush\Repositories\WebPushPreferenceRepositoryInterface;
use App\Infrastructure\Mail\Preferences\MailCategoryAudience;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * The person's push switches per optional category with site notices (core#804). Asked only for
 * notices the bell got, so a muted subject or a category turned off on the site is never pushed
 * either. Mandatory categories are never pushed: the bell and the mail already carry them. Fails
 * closed: when the choices cannot be read, nothing is pushed.
 */
final readonly class WebPushPreferenceService implements WebPushPreferenceInterface, WebPushPreferenceServiceInterface
{
    public function __construct(
        private MailCategoryRegistryInterface $categories,
        private WebPushPreferenceRepositoryInterface $preferences,
    ) {}

    public function wantsPush(string $userId, MailCategoryDTO $category, ?MailSubjectDTO $subject): bool
    {
        if (! $category->site || $category->isMandatory()) {
            return false;
        }

        try {
            return in_array($category->key, $this->preferences->enabledFor(UserId::fromString($userId)), true);
        } catch (Throwable $exception) {
            // The notice is in the bell already: a push less is better than a push not chosen
            Log::warning('Could not read the push notification choices; nothing is pushed.', [
                'category' => $category->key,
                'user_id' => $userId,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    public function enabledFor(object $user): array
    {
        if (! $user instanceof UserModel) {
            return [];
        }

        try {
            return $this->preferences->enabledFor(UserId::fromString($user->id));
        } catch (Throwable $exception) {
            Log::warning('Could not read the push notification choices.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);

            return [];
        }
    }

    public function update(object $user, array $changes): void
    {
        if (! $user instanceof UserModel) {
            throw new InvalidArgumentException('Only accounts have push notification preferences.');
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

            // Listed apart, without switches: always in the bell and the mail, never pushed
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

    public function forgetUser(string $userId): void
    {
        $this->preferences->deleteForUser(UserId::fromString($userId));
    }
}
