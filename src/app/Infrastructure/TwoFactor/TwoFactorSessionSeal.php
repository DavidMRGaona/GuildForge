<?php

declare(strict_types=1);

namespace App\Infrastructure\TwoFactor;

use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Domain\TwoFactor\Exceptions\TwoFactorUnavailableException;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The seal lives in users.session_seal (hidden from serialization; read from the account already
 * loaded for the request, so a session holding it costs no query) and under SESSION_KEY in the
 * session, compared in constant time.
 *
 * The challenge's «remember me» cookie (REMEMBER_COOKIE, encrypted and HttpOnly like every site
 * cookie) holds an HMAC of the account id, its remember token and its seal: a new remember token
 * or seal invalidates it.
 */
final readonly class TwoFactorSessionSeal implements TwoFactorSessionSealInterface
{
    public const string SESSION_KEY = 'two_factor_seal';

    public const string REMEMBER_COOKIE = 'two_factor_remember';

    public const int LENGTH = 64;

    /** Laravel's own «remember me» lifetime (SessionGuard::$rememberDuration, 400 days) */
    public const int REMEMBER_MINUTES = 576000;

    public function __construct(
        private CookieJar $cookies,
        #[\SensitiveParameter]
        private string $key,
    ) {}

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
        $userId = null;

        try {
            $userId = (string) $owner->getAuthIdentifier();
            $seal = $owner instanceof UserModel ? $owner->getAttribute('session_seal') : $this->sealOf($userId);

            if ($seal === null || $seal === '') {
                // Two-factor authentication on without a seal is data out of step: keep no session
                return ! $this->twoFactorEnabled($userId);
            }
        } catch (Throwable $exception) {
            // Not knowing is not a mismatch: nobody is let through, nor is the session closed
            Log::error('Session seal could not be read; the request is refused.', [
                'user_id' => $userId,
                'exception' => $exception::class,
            ]);

            throw new TwoFactorUnavailableException;
        }

        $held = $session->get(self::SESSION_KEY);

        return is_string($seal) && is_string($held) && hash_equals($seal, $held);
    }

    public function renew(string $userId, ?Session $keep = null): void
    {
        $id = $this->normalise($userId);
        if ($id === null) {
            return;
        }

        $seal = Str::random(self::LENGTH);

        // Now, in the caller's transaction: a failure here rolls the change back with it
        UserModel::query()->withTrashed()->whereKey($id)->update([
            'session_seal' => $seal,
            'remember_token' => Str::random(60),
        ]);

        DB::afterCommit(static function () use ($id, $seal, $keep): void {
            $keep?->put(self::SESSION_KEY, $seal);

            try {
                self::deleteOtherSessionRows($id, $keep?->getId());
            } catch (Throwable $exception) {
                Log::error('Other sessions could not be deleted after renewing the session seal.', [
                    'user_id' => $id,
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    public function rememberAfterChallenge(string $userId): void
    {
        $id = $this->normalise($userId);

        if ($id === null) {
            // Never the text as it came: it is no id, so it may be anything
            Log::warning('Two-factor «remember me» cookie not issued: not an account id.', ['user_id' => null]);

            return;
        }

        $value = $this->rememberValue($id);

        if ($value === null) {
            // Called before login($user, true) gave the account a remember token, or without a seal
            Log::warning('Two-factor «remember me» cookie not issued: the account has no remember token or seal yet.', [
                'user_id' => $id,
            ]);

            return;
        }

        $this->cookies->queue($this->cookies->make(self::REMEMBER_COOKIE, $value, self::REMEMBER_MINUTES, httpOnly: true));
    }

    public function rememberedAfterChallenge(Request $request, string $userId): bool
    {
        $held = $request->cookie(self::REMEMBER_COOKIE);
        $id = $this->normalise($userId);

        if (! is_string($held) || $held === '' || $id === null) {
            return false;
        }

        try {
            $expected = $this->rememberValue($id);
        } catch (Throwable $exception) {
            Log::error('Two-factor «remember me» check failed; the cookie is refused.', [
                'user_id' => $id,
                'exception' => $exception::class,
            ]);

            return false;
        }

        return $expected !== null && hash_equals($expected, $held);
    }

    /**
     * Null while the account has no remember token or no seal: nothing to bind the cookie to.
     */
    private function rememberValue(string $id): ?string
    {
        $row = UserModel::query()->withTrashed()->whereKey($id)->first(['remember_token', 'session_seal']);
        $token = $row?->getAttribute('remember_token');
        $seal = $row?->getAttribute('session_seal');

        if (! is_string($token) || $token === '' || ! is_string($seal) || $seal === '') {
            return null;
        }

        return hash_hmac('sha256', $id.'|'.$token.'|'.$seal, 'two-factor-remember|'.$this->key);
    }

    private static function deleteOtherSessionRows(string $id, ?string $keepId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $connection = config('session.connection');
        $table = config('session.table');

        $sessions = DB::connection(is_string($connection) ? $connection : null);

        // A savepoint of its own: a failure must not abort a transaction still open around it
        $sessions->transaction(static fn (): int => $sessions
            ->table(is_string($table) ? $table : 'sessions')
            ->where('user_id', $id)
            ->when($keepId !== null, static fn ($query) => $query->where('id', '!=', $keepId))
            ->delete());
    }

    private function twoFactorEnabled(string $userId): bool
    {
        $id = $this->normalise($userId);

        return $id !== null && UserTwoFactorModel::query()->whereKey($id)->whereNotNull('confirmed_at')->exists();
    }

    private function sealOf(string $userId): ?string
    {
        $id = $this->normalise($userId);
        if ($id === null) {
            return null;
        }

        $seal = UserModel::query()->withTrashed()->whereKey($id)->value('session_seal');

        return is_string($seal) && $seal !== '' ? $seal : null;
    }

    private function normalise(string $userId): ?string
    {
        $id = strtolower(trim($userId));

        return Str::isUuid($id) ? $id : null;
    }
}
