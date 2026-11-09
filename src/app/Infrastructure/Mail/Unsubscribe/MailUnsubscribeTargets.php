<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Unsubscribe;

use App\Application\Mail\DTOs\MailPreferenceDTO;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

/**
 * Finds what a signed unsubscribe link points at. The signature already proves the account and
 * the category were put in the link by the site; this only checks they still exist.
 */
final readonly class MailUnsubscribeTargets
{
    public function __construct(
        private MailPreferenceServiceInterface $preferences,
    ) {}

    /**
     * Null for a deactivated or anonymized account, an unknown category (a module disabled
     * since) or one the person is no longer offered.
     */
    public function find(string $userId, string $categoryKey): ?MailUnsubscribeTarget
    {
        // Soft-deleted (deactivated) accounts are left out by the default scope
        $user = UserModel::query()->whereKey($userId)->whereNull('anonymized_at')->first();

        if (! $user instanceof UserModel) {
            return null;
        }

        $preference = $this->preferenceOf($user, $categoryKey);

        return $preference instanceof MailPreferenceDTO ? new MailUnsubscribeTarget($user, $preference) : null;
    }

    private function preferenceOf(UserModel $user, string $categoryKey): ?MailPreferenceDTO
    {
        foreach ($this->preferences->sectionsFor($user) as $section) {
            foreach ($section->categories as $preference) {
                if ($preference->key === $categoryKey) {
                    return $preference;
                }
            }
        }

        return null;
    }
}
