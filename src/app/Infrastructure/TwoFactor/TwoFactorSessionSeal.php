<?php

declare(strict_types=1);

namespace App\Infrastructure\TwoFactor;

use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The seal lives in users.session_seal (hidden from serialization; read from the account already
 * loaded for the request, so the check costs no query) and under SESSION_KEY in the session,
 * compared in constant time.
 */
final readonly class TwoFactorSessionSeal implements TwoFactorSessionSealInterface
{
    public const string SESSION_KEY = 'two_factor_seal';

    public const int LENGTH = 64;

    public function stamp(Session $session, string $userId): void
    {
        $seal = $this->sealOf($userId);

        if ($seal === null) {
            $session->forget(self::SESSION_KEY);

            return;
        }

        $session->put(self::SESSION_KEY, $seal);
    }

    public function passes(Session $session, Authenticatable $owner): bool
    {
        try {
            $seal = $owner instanceof UserModel
                ? $owner->getAttribute('session_seal')
                : $this->sealOf((string) $owner->getAuthIdentifier());
        } catch (Throwable) {
            return false;
        }

        if ($seal === null) {
            return true;
        }

        $held = $session->get(self::SESSION_KEY);

        return is_string($seal) && $seal !== '' && is_string($held) && hash_equals($seal, $held);
    }

    public function renew(string $userId, ?Session $keep = null): void
    {
        $id = strtolower(trim($userId));
        if (! Str::isUuid($id)) {
            return;
        }

        DB::afterCommit(static function () use ($id, $keep): void {
            $seal = Str::random(self::LENGTH);

            UserModel::query()->withTrashed()->whereKey($id)->update([
                'session_seal' => $seal,
                'remember_token' => Str::random(60),
            ]);

            $keep?->put(self::SESSION_KEY, $seal);

            self::deleteOtherSessionRows($id, $keep?->getId());
        });
    }

    /**
     * Only a clean-up: the new seal already signs them out with any driver.
     */
    private static function deleteOtherSessionRows(string $id, ?string $keepId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $connection = config('session.connection');
        $table = config('session.table');

        DB::connection(is_string($connection) ? $connection : null)
            ->table(is_string($table) ? $table : 'sessions')
            ->where('user_id', $id)
            ->when($keepId !== null, static fn ($query) => $query->where('id', '!=', $keepId))
            ->delete();
    }

    private function sealOf(string $userId): ?string
    {
        $id = strtolower(trim($userId));
        if (! Str::isUuid($id)) {
            return null;
        }

        $seal = UserModel::query()->withTrashed()->whereKey($id)->value('session_seal');

        return is_string($seal) && $seal !== '' ? $seal : null;
    }
}
