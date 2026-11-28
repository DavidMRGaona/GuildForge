<?php

declare(strict_types=1);

namespace App\Application\Bots\Services;

use App\Application\Authorization\Services\BaseRoleRule;
use App\Application\TwoFactor\Services\TwoFactorStatusReaderInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Throwable;

/**
 * The one rule for which existing accounts may become a bot (core#863). Adopting wipes the login, so it
 * only takes an account that could never have belonged to a person: an email on an `.invalid` domain,
 * never verified, no two-factor, no role but the base one, not anonymized and not another bot's.
 * Fails closed: anything that cannot be read refuses.
 */
final readonly class BotAdoptionRule
{
    public function __construct(
        private TwoFactorStatusReaderInterface $twoFactor,
        private BaseRoleRule $baseRole,
    ) {}

    public function allows(UserModel $account, string $key): bool
    {
        try {
            if ($account->anonymized_at !== null || $account->email_verified_at !== null) {
                return false;
            }

            $botKey = $account->bot_key;
            if ($botKey !== null && $botKey !== '') {
                return $botKey === $key;
            }

            if (! $this->hasInvalidDomain((string) $account->email)) {
                return false;
            }

            /** @var list<string> $roles */
            $roles = $account->roles()->pluck('roles.name')->all();
            foreach ($roles as $role) {
                if (! $this->baseRole->isBaseRoleName($role)) {
                    return false;
                }
            }

            return ! $this->twoFactor->isEnabled(strtolower((string) $account->getKey()));
        } catch (Throwable) {
            return false;
        }
    }

    private function hasInvalidDomain(string $email): bool
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }

        $domain = strtolower(substr($email, $at + 1));

        return $domain === 'invalid' || str_ends_with($domain, '.invalid');
    }
}
