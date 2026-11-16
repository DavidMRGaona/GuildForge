<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Application\TwoFactor\Services\TwoFactorChallengeInterface;
use App\Domain\TwoFactor\TwoFactorChallengeRule;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * The panel's login page (core#770). Filament asks for the second factor in its own step, through
 * TwoFactorAuthenticationProvider; the password step neither signs in nor queues any cookie.
 * This page adds what the site's challenge has too:
 *
 * - A pending sign-in lasts TwoFactorChallengeRule::PENDING_SECONDS and is dropped after
 *   MAX_FAILURES wrong codes, then the password has to be given again. Both are kept on the
 *   server, by the pending sign-in Filament hands the browser, so replaying an older state of the
 *   page does not give the attempts back.
 * - Once the code is accepted and Filament signed in: regenerate, stamp the session seal and,
 *   with «remember me», the challenge's cookie (TwoFactorChallengeInterface::completeSignIn()).
 *
 * Who may enter the panel at all stays Filament's isUserAllowedToAccessPanel(): the place for
 * telling people why their panel access is paused (core#772).
 */
final class PanelLogin extends Login
{
    private const string PENDING_CACHE_PREFIX = 'two_factor:panel_pending:';

    public function authenticate(): ?LoginResponse
    {
        $pendingKey = $this->pendingKey();

        if ($pendingKey !== null && ! is_array(Cache::get($pendingKey))) {
            $this->signInAgain('auth.two_factor.sign_in_again');

            return null;
        }

        $provider = $this->twoFactorProvider();

        try {
            $response = parent::authenticate();
            $check = $provider?->lastCheck();
        } catch (ValidationException $exception) {
            if ($pendingKey !== null && $provider?->lastCheck()?->countsAsFailure() === true && ! $this->recordFailure($pendingKey)) {
                $this->signInAgain('auth.two_factor.too_many_failures');

                return null;
            }

            throw $exception;
        } finally {
            $provider?->forgetChecks();
        }

        if ($pendingKey === null) {
            // The password step just opened a challenge: start counting its time and failures
            $opened = $this->pendingKey();

            if (! $response instanceof LoginResponse && $opened !== null) {
                Cache::put($opened, ['started_at' => Carbon::now()->getTimestamp(), 'failures' => 0], TwoFactorChallengeRule::PENDING_SECONDS);
            }

            return $response;
        }

        if ($response instanceof LoginResponse && Filament::auth()->check()) {
            Cache::forget($pendingKey);
            app(TwoFactorChallengeInterface::class)->completeSignIn(
                session()->driver(),
                (string) Filament::auth()->id(),
                (bool) ($this->data['remember'] ?? false),
            );
            $this->announceRecoveryCodesLeft($check?->recoveryCodesRemaining);
        }

        return $response;
    }

    private function recordFailure(string $pendingKey): bool
    {
        $state = Cache::get($pendingKey);

        if (! is_array($state) || ! is_int($state['started_at'] ?? null) || ! is_int($state['failures'] ?? null)) {
            return false;
        }

        $failures = $state['failures'] + 1;

        if (TwoFactorChallengeRule::isExhausted($failures)) {
            Cache::forget($pendingKey);

            return false;
        }

        $left = TwoFactorChallengeRule::PENDING_SECONDS - (Carbon::now()->getTimestamp() - $state['started_at']);

        if ($left <= 0) {
            Cache::forget($pendingKey);

            return false;
        }

        Cache::put($pendingKey, ['started_at' => $state['started_at'], 'failures' => $failures], $left);

        return true;
    }

    /**
     * Back to the password step, saying why.
     */
    private function signInAgain(string $message): void
    {
        $this->userUndertakingMultiFactorAuthentication = null;
        $this->form->fill([
            'email' => $this->data['email'] ?? null,
            'remember' => $this->data['remember'] ?? false,
        ]);

        Notification::make()
            ->title(__($message))
            ->warning()
            ->send();
    }

    private function announceRecoveryCodesLeft(?int $left): void
    {
        if ($left === null) {
            return;
        }

        Notification::make()
            ->title(trans_choice('auth.two_factor.recovery_codes_left', $left, ['count' => $left]))
            ->warning()
            ->persistent()
            ->send();
    }

    private function pendingKey(): ?string
    {
        $pending = $this->userUndertakingMultiFactorAuthentication;

        return is_string($pending) && $pending !== ''
            ? self::PENDING_CACHE_PREFIX.hash('sha256', $pending)
            : null;
    }

    private function twoFactorProvider(): ?TwoFactorAuthenticationProvider
    {
        foreach (Filament::getCurrentOrDefaultPanel()?->getMultiFactorAuthenticationProviders() ?? [] as $provider) {
            if ($provider instanceof TwoFactorAuthenticationProvider) {
                return $provider;
            }
        }

        return null;
    }
}
