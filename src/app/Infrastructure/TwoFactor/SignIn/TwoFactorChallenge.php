<?php

declare(strict_types=1);

namespace App\Infrastructure\TwoFactor\SignIn;

use App\Application\TwoFactor\DTOs\TwoFactorChallengeCheckDTO;
use App\Application\TwoFactor\Services\TwoFactorChallengeInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Domain\Events\UserLoggedIn;
use App\Domain\TwoFactor\Events\RepeatedTwoFactorCodeFailures;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use App\Domain\TwoFactor\TwoFactorChallengeRule;
use App\Domain\TwoFactor\TwoFactorFailure;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The account's limits live here, so the site and the panel share them: every code checked counts,
 * whichever of the two it came from, whatever the pending sign-in or the address.
 */
final readonly class TwoFactorChallenge implements TwoFactorChallengeInterface
{
    private const string ATTEMPTS_KEY = 'two-factor-challenge:account:';

    private const string FAILURES_KEY = 'two-factor-challenge:failures:';

    private const int MINUTE = 60;

    private const int HOUR = 3600;

    public function __construct(
        private TwoFactorServiceInterface $twoFactor,
        private TwoFactorSessionSealInterface $seal,
        private AuthFactory $auth,
        private Dispatcher $events,
        private RateLimiter $limiter,
    ) {}

    public function check(string $userId, #[\SensitiveParameter] string $code, bool $recoveryCode): TwoFactorChallengeCheckDTO
    {
        $result = $this->limited($userId) ?? $this->checkCode($userId, $code, $recoveryCode);

        if (! $result->passed) {
            Log::warning('Two-factor sign-in code refused.', [
                'user_id' => $userId,
                'reason' => $recoveryCode && $result->failure === TwoFactorFailure::InvalidCode
                    ? 'invalid_recovery_code'
                    : $result->failure?->value,
            ]);
        }

        if ($result->countsAsFailure()) {
            $this->countFailure($userId);
        }

        return $result;
    }

    public function signIn(Session $session, string $userId, bool $remember): bool
    {
        $guard = $this->guard();
        $user = $guard->getProvider()->retrieveById($userId);

        if (! $user instanceof UserModel) {
            return false;
        }

        $guard->login($user, $remember);
        $this->completeSignIn($session, $userId, $remember);

        $this->events->dispatch(new UserLoggedIn($userId, $user->email));

        return true;
    }

    public function completeSignIn(Session $session, string $userId, bool $remember): void
    {
        $session->regenerate();
        $this->seal->stamp($session, $userId);

        if ($remember) {
            $this->seal->rememberAfterChallenge($userId);
        }
    }

    /**
     * The account's limit reached: refused without asking the service. Otherwise the code counts.
     */
    private function limited(string $userId): ?TwoFactorChallengeCheckDTO
    {
        $minute = self::ATTEMPTS_KEY.$userId;
        $hour = $minute.':hour';
        $wait = 0;

        if ($this->limiter->tooManyAttempts($minute, TwoFactorChallengeRule::ATTEMPTS_PER_MINUTE)) {
            $wait = $this->limiter->availableIn($minute);
        }

        if ($this->limiter->tooManyAttempts($hour, TwoFactorChallengeRule::ATTEMPTS_PER_HOUR)) {
            $wait = max($wait, $this->limiter->availableIn($hour));
        }

        if ($wait > 0) {
            return TwoFactorChallengeCheckDTO::tooManyAttempts($wait);
        }

        $this->limiter->hit($minute, self::MINUTE);
        $this->limiter->hit($hour, self::HOUR);

        return null;
    }

    private function checkCode(string $userId, #[\SensitiveParameter] string $code, bool $recoveryCode): TwoFactorChallengeCheckDTO
    {
        try {
            return $recoveryCode ? $this->useRecoveryCode($userId, $code) : $this->verify($userId, $code);
        } catch (TwoFactorException) {
            return TwoFactorChallengeCheckDTO::refused(TwoFactorFailure::Unavailable);
        }
    }

    /**
     * Counted atomically by the cache; exactly the tenth wrong code within the hour tells the owner.
     */
    private function countFailure(string $userId): void
    {
        if ($this->limiter->hit(self::FAILURES_KEY.$userId, self::HOUR) === TwoFactorChallengeRule::ALERT_AFTER_FAILURES) {
            $this->events->dispatch(new RepeatedTwoFactorCodeFailures($userId));
        }
    }

    private function verify(string $userId, #[\SensitiveParameter] string $code): TwoFactorChallengeCheckDTO
    {
        return $this->twoFactor->verify($userId, $code)
            ? TwoFactorChallengeCheckDTO::passed()
            : TwoFactorChallengeCheckDTO::refused(TwoFactorFailure::InvalidCode);
    }

    private function useRecoveryCode(string $userId, #[\SensitiveParameter] string $code): TwoFactorChallengeCheckDTO
    {
        if (! $this->twoFactor->useRecoveryCode($userId, $code)) {
            return TwoFactorChallengeCheckDTO::refused(TwoFactorFailure::InvalidCode);
        }

        return TwoFactorChallengeCheckDTO::passed($this->twoFactor->status($userId)->recoveryCodesRemaining);
    }

    private function guard(): SessionGuard
    {
        $guard = $this->auth->guard('web');

        if (! $guard instanceof SessionGuard) {
            throw new RuntimeException('The site guard is not a session guard.');
        }

        return $guard;
    }
}
