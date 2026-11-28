<?php

declare(strict_types=1);

namespace App\Infrastructure\Bots;

use App\Application\Authorization\Services\BaseRoleRule;
use App\Application\TwoFactor\Services\TwoFactorStatusReaderInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Support\DailyWarnings;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The one rule for which existing accounts may become a bot (core#863). Adopting wipes the login, so it
 * only takes an account that could never have belonged to a person: an email on an `.invalid` domain,
 * never verified, no two-factor, no role but the base one, not anonymized and not another bot's.
 * Fails closed: anything that cannot be read refuses.
 */
final readonly class BotAdoptionRule
{
    private DailyWarnings $warnings;

    public function __construct(
        private TwoFactorStatusReaderInterface $twoFactor,
        private BaseRoleRule $baseRole,
    ) {
        $this->warnings = new DailyWarnings('bots:warned:', static fn () => Cache::store());
    }

    public function allows(UserModel $account, string $key): bool
    {
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

        return ! $this->hasTwoFactor(strtolower((string) $account->getKey()));
    }

    /**
     * Only this call is guarded: when two-factor cannot be read the account is refused, and the
     * refusal is told apart from a real one in the log. Any other failure goes up.
     */
    private function hasTwoFactor(string $userId): bool
    {
        try {
            return $this->twoFactor->isEnabled($userId);
        } catch (Throwable $e) {
            $this->warnings->warn(
                'adoption-two-factor-unreadable:'.$userId,
                'Bot adoption refused: the two-factor state could not be read',
                ['user_id' => $userId, 'exception' => $e::class],
            );

            // Fail closed
            return true;
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
