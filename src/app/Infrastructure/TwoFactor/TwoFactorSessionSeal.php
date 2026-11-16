<?php

declare(strict_types=1);

namespace App\Infrastructure\TwoFactor;

use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use Throwable;

/**
 * The seal lives in user_two_factor.session_seal (set when it is confirmed, gone with the row) and
 * under SESSION_KEY in the session, compared in constant time.
 */
final readonly class TwoFactorSessionSeal implements TwoFactorSessionSealInterface
{
    public const string SESSION_KEY = 'two_factor_seal';

    public function stamp(Session $session, string $userId): void
    {
        $seal = $this->sealOf($userId);

        if ($seal === null) {
            $session->forget(self::SESSION_KEY);

            return;
        }

        $session->put(self::SESSION_KEY, $seal);
    }

    public function passes(Session $session, string $userId): bool
    {
        try {
            $seal = $this->sealOf($userId);
        } catch (Throwable) {
            return false;
        }

        if ($seal === null) {
            return true;
        }

        $held = $session->get(self::SESSION_KEY);

        return is_string($held) && hash_equals($seal, $held);
    }

    /**
     * Null without two-factor authentication on (no row, or a setup not confirmed).
     */
    private function sealOf(string $userId): ?string
    {
        $id = strtolower(trim($userId));
        if (! Str::isUuid($id)) {
            return null;
        }

        $seal = UserTwoFactorModel::query()->whereKey($id)->whereNotNull('confirmed_at')->value('session_seal');

        return is_string($seal) && $seal !== '' ? $seal : null;
    }
}
