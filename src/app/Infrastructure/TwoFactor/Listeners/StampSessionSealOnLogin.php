<?php

declare(strict_types=1);

namespace App\Infrastructure\TwoFactor\Listeners;

use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Application\TwoFactor\Services\TwoFactorStatusReaderInterface;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Copies the account's seal into the session it signs in with (TwoFactorSessionSealInterface).
 *
 * An account with two-factor authentication on gets it here only when restored from a «remember
 * me» cookie: those are issued after the challenge, and older ones stopped working when the seal
 * changed. Any other sign-in of such an account must go through the challenge, which stamps the
 * session itself (core#770); one that skipped it is signed out on its next request.
 */
final readonly class StampSessionSealOnLogin
{
    public function __construct(
        private Container $container,
        private AuthFactory $auth,
        private TwoFactorSessionSealInterface $seal,
        private TwoFactorStatusReaderInterface $twoFactor,
    ) {}

    public function handle(Login $event): void
    {
        if (! $this->container->bound('session.store')) {
            return;
        }

        $userId = (string) $event->user->getAuthIdentifier();
        $guard = $this->auth->guard($event->guard);
        $viaRemember = $guard instanceof StatefulGuard && $guard->viaRemember();

        try {
            // Unknown means on: no seal, so the session is signed out rather than let through
            if (! $viaRemember && $this->twoFactor->isEnabled($userId)) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $this->seal->stamp($this->container->make('session.store'), $userId);
    }
}
