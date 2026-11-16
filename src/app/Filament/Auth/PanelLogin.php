<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Application\TwoFactor\Services\TwoFactorChallengeInterface;
use App\Domain\TwoFactor\TwoFactorChallengeRule;
use App\Infrastructure\Authorization\Services\TwoFactorPanelAccess;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
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
 * Who may enter the panel at all stays Filament's isUserAllowedToAccessPanel(). Someone kept out
 * only because roles that require two-factor authentication are paused (core#772) is told so,
 * with a link to «Seguridad», instead of the generic refusal. Filament asks it once the password
 * was checked, so the message never tells a stranger anything about an account.
 */
final class PanelLogin extends Login
{
    private const string PENDING_CACHE_PREFIX = 'two_factor:panel_pending:';

    private const string FAILURES_SUFFIX = ':failures';

    public function authenticate(): ?LoginResponse
    {
        $pendingKey = $this->pendingKey();

        if ($pendingKey !== null && ! is_int(Cache::get($pendingKey))) {
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
                Cache::put($opened, Carbon::now()->getTimestamp(), TwoFactorChallengeRule::PENDING_SECONDS);
                Cache::put($opened.self::FAILURES_SUFFIX, 0, TwoFactorChallengeRule::PENDING_SECONDS);
            }

            return $response;
        }

        if ($response instanceof LoginResponse && Filament::auth()->check()) {
            $this->forgetPending($pendingKey);
            app(TwoFactorChallengeInterface::class)->completeSignIn(
                session()->driver(),
                (string) Filament::auth()->id(),
                (bool) ($this->data['remember'] ?? false),
            );
            $this->announceRecoveryCodesLeft($check?->recoveryCodesRemaining);
        }

        return $response;
    }

    /**
     * Counts a wrong code with an atomic increment, so requests sent at once cannot both read the
     * same count. False when that was the last one allowed, or the pending sign-in is gone.
     */
    private function recordFailure(string $pendingKey): bool
    {
        // Some stores create a missing key again without an expiry when incremented: a pending
        // sign-in already gone is an expired one, and its counter is never touched
        if (! is_int(Cache::get($pendingKey))) {
            $this->forgetPending($pendingKey);

            return false;
        }

        $failures = Cache::increment($pendingKey.self::FAILURES_SUFFIX);

        if (! is_int($failures) || TwoFactorChallengeRule::isExhausted($failures)) {
            $this->forgetPending($pendingKey);

            return false;
        }

        return true;
    }

    private function forgetPending(string $pendingKey): void
    {
        Cache::forget($pendingKey);
        Cache::forget($pendingKey.self::FAILURES_SUFFIX);
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

    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if (parent::isUserAllowedToAccessPanel($user)) {
            return true;
        }

        if (app(TwoFactorPanelAccess::class)->pausedOnlyByTwoFactor($user)) {
            Notification::make()
                ->title(__('two_factor.roles.panel_paused'))
                ->warning()
                ->persistent()
                ->actions([
                    Action::make('security')
                        ->label(__('two_factor.roles.panel_paused_action'))
                        ->url(route('profile.show', ['tab' => 'seguridad'])),
                ])
                ->send();

            throw ValidationException::withMessages([
                'data.email' => __('two_factor.roles.panel_paused'),
            ]);
        }

        return false;
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
