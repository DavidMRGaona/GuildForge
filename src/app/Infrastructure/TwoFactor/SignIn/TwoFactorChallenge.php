<?php

declare(strict_types=1);

namespace App\Infrastructure\TwoFactor\SignIn;

use App\Application\TwoFactor\DTOs\TwoFactorChallengeCheckDTO;
use App\Application\TwoFactor\Services\TwoFactorChallengeInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Domain\Events\UserLoggedIn;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use App\Domain\TwoFactor\TwoFactorFailure;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class TwoFactorChallenge implements TwoFactorChallengeInterface
{
    public function __construct(
        private TwoFactorServiceInterface $twoFactor,
        private TwoFactorSessionSealInterface $seal,
        private AuthFactory $auth,
        private Dispatcher $events,
    ) {}

    public function check(string $userId, #[\SensitiveParameter] string $code, bool $recoveryCode): TwoFactorChallengeCheckDTO
    {
        try {
            $result = $recoveryCode ? $this->useRecoveryCode($userId, $code) : $this->verify($userId, $code);
        } catch (TwoFactorException) {
            $result = TwoFactorChallengeCheckDTO::refused(TwoFactorFailure::Unavailable);
        }

        if (! $result->passed) {
            Log::warning('Two-factor sign-in code refused.', [
                'user_id' => $userId,
                'reason' => $result->failure?->value,
                'recovery_code' => $recoveryCode,
            ]);
        }

        return $result;
    }

    public function signIn(Session $session, string $userId, bool $remember): void
    {
        $guard = $this->guard();
        $user = $guard->getProvider()->retrieveById($userId);

        if (! $user instanceof UserModel) {
            throw new RuntimeException('The account of the pending sign-in no longer exists.');
        }

        $guard->login($user, $remember);
        $this->completeSignIn($session, $userId, $remember);

        $this->events->dispatch(new UserLoggedIn($userId, $user->email));
    }

    public function completeSignIn(Session $session, string $userId, bool $remember): void
    {
        $session->regenerate();
        $this->seal->stamp($session, $userId);

        if ($remember) {
            $this->seal->rememberAfterChallenge($userId);
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
