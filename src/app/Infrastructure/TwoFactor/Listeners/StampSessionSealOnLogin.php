<?php

declare(strict_types=1);

namespace App\Infrastructure\TwoFactor\Listeners;

use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Application\TwoFactor\Services\TwoFactorStatusReaderInterface;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copies the account's seal into the session it signs in with (TwoFactorSessionSealInterface).
 *
 * An account with two-factor authentication on never gets it from a plain sign-in: the challenge
 * stamps the session itself (core#770). Restored from a «remember me» cookie, it gets it only when
 * the request also carries the cookie the challenge issued, since Laravel's own cookie can be
 * issued with the password alone and says nothing about the challenge. Without the seal, the
 * session is signed out on its next request.
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

        /** @var Session $session */
        $session = $this->container->make('session.store');
        $userId = (string) $event->user->getAuthIdentifier();

        try {
            if ($this->twoFactor->isEnabled($userId) && ! $this->rememberedAfterChallenge($event, $userId)) {
                $session->forget(TwoFactorSessionSeal::SESSION_KEY);

                return;
            }

            $this->seal->stamp($session, $userId);
        } catch (Throwable $exception) {
            // Unknown means no seal: the session is signed out rather than let through
            $session->forget(TwoFactorSessionSeal::SESSION_KEY);
            Log::error('Session seal could not be copied at sign-in.', [
                'user_id' => $userId,
                'exception' => $exception::class,
            ]);
        }
    }

    private function rememberedAfterChallenge(Login $event, string $userId): bool
    {
        $guard = $this->auth->guard($event->guard);

        if (! $guard instanceof StatefulGuard || ! $guard->viaRemember() || ! $this->container->bound('request')) {
            return false;
        }

        $request = $this->container->make('request');

        return $this->seal->rememberedAfterChallenge($request, $userId);
    }
}
