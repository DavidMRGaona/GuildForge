<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Authorization\Services\TwoFactorPanelAccess;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Exceptions\HttpResponseException;
use Throwable;

/**
 * Filament's panel authentication (core#772): a signed-in account kept out only because roles
 * that require two-factor authentication are paused goes to «Ajustes de la cuenta → Seguridad»
 * with a notice saying why, instead of the generic 403. Anything else (a guest, access granted,
 * another reason, a check that fails) is decided by Filament as always.
 *
 * A subclass rather than a middleware of its own: Laravel's middleware priority moves
 * Authenticate ahead of any middleware listed before it, which would then never run first.
 */
final class AuthenticatePanel extends Authenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        if ($this->pausedOnlyByTwoFactor()) {
            throw new HttpResponseException(redirect()
                ->route('profile.show', ['tab' => 'seguridad'])
                ->with('warning', __('two_factor.roles.panel_paused')));
        }

        parent::authenticate($request, $guards);
    }

    /**
     * Fails closed to Filament's own answer: a check that throws never opens anything.
     */
    private function pausedOnlyByTwoFactor(): bool
    {
        try {
            $user = Filament::auth()->user();

            return $user !== null && app(TwoFactorPanelAccess::class)->pausedOnlyByTwoFactor($user);
        } catch (Throwable) {
            return false;
        }
    }
}
