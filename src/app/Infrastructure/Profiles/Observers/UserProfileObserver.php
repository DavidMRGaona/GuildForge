<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Observers;

use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps the public profile row in step with the account (core#740), whatever path creates or changes it:
 * registration, the panel, `make:admin-user`, seeders. Creating an account or saving its public name never
 * fails because of the profile: the error is logged and `profiles:create-missing` creates a missing row
 * the next day.
 */
final readonly class UserProfileObserver
{
    public function __construct(
        private UserProfileServiceInterface $profiles,
    ) {}

    public function created(UserModel $user): void
    {
        // A bot is not a person: no public profile (core#863)
        if ($user->isBot()) {
            return;
        }

        try {
            $this->profiles->createFor((string) $user->getKey());
        } catch (Throwable $e) {
            Log::error('[Profiles] The public profile of a new account could not be created; profiles:create-missing will.', [
                'user_id' => (string) $user->getKey(),
                'exception' => $e,
            ]);
        }
    }

    public function updated(UserModel $user): void
    {
        if ($user->isBot() || ! $user->wasChanged('display_name') || $user->anonymized_at !== null) {
            return;
        }

        try {
            $this->profiles->followPublicName((string) $user->getKey());
        } catch (Throwable $e) {
            Log::error('[Profiles] The public profile address could not follow the new public name.', [
                'user_id' => (string) $user->getKey(),
                'exception' => $e,
            ]);
        }
    }

    /**
     * Before the row goes with the account (foreign key): its address stays reserved, naming nobody. A
     * failure stops the deletion, so an address is never freed for somebody else.
     */
    public function forceDeleting(UserModel $user): void
    {
        $this->profiles->forget((string) $user->getKey());
    }
}
