<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\TwoFactor\Services\TwoFactorResetInterface;
use App\Application\TwoFactor\Services\PendingTwoFactorLoginInterface;
use App\Application\TwoFactor\Services\TwoFactorChallengeInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Application\TwoFactor\Services\TwoFactorStatusReaderInterface;
use App\Domain\TwoFactor\Events\RecoveryCodesRegenerated;
use App\Domain\TwoFactor\Events\RecoveryCodeUsed;
use App\Domain\TwoFactor\Events\TwoFactorDisabled;
use App\Domain\TwoFactor\Events\TwoFactorEnabled;
use App\Domain\TwoFactor\Events\TwoFactorReset;
use App\Infrastructure\QrCodes\QrCodeContentPolicy;
use App\Infrastructure\QrCodes\TotpQrCodeRenderer;
use App\Infrastructure\QrCodes\TotpQrCodeRendererInterface;
use App\Infrastructure\TwoFactor\Listeners\SendTwoFactorSecurityNotice;
use App\Infrastructure\TwoFactor\Listeners\StampSessionSealOnLogin;
use App\Infrastructure\TwoFactor\TwoFactorResetService;
use App\Infrastructure\TwoFactor\SignIn\SessionPendingTwoFactorLogin;
use App\Infrastructure\TwoFactor\SignIn\TwoFactorChallenge;
use App\Infrastructure\TwoFactor\TwoFactorService;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Two-factor authentication (core#197): the service every sign-in and setting goes through, the
 * QR path of its own for authenticator URIs, and the security mails of each change.
 */
final class TwoFactorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TwoFactorServiceInterface::class, TwoFactorService::class);
        $this->app->bind(TwoFactorStatusReaderInterface::class, TwoFactorService::class);
        $this->app->bind(TwoFactorResetInterface::class, TwoFactorResetService::class);
        $this->app->bind(TwoFactorSessionSealInterface::class, static fn ($app): TwoFactorSessionSeal => new TwoFactorSessionSeal(
            $app->make(CookieJar::class),
            (string) config('app.key'),
        ));

        $this->app->bind(TwoFactorChallengeInterface::class, TwoFactorChallenge::class);
        $this->app->bind(PendingTwoFactorLoginInterface::class, SessionPendingTwoFactorLogin::class);

        $this->app->bind(TotpQrCodeRendererInterface::class, static fn ($app): TotpQrCodeRendererInterface => new TotpQrCodeRenderer(
            new QrCodeContentPolicy((string) config('app.url'), $app->environment('local', 'testing')),
        ));
    }

    public function boot(): void
    {
        Event::listen(Login::class, StampSessionSealOnLogin::class);
        Event::listen(TwoFactorEnabled::class, [SendTwoFactorSecurityNotice::class, 'enabled']);
        Event::listen(TwoFactorDisabled::class, [SendTwoFactorSecurityNotice::class, 'disabled']);
        Event::listen(TwoFactorReset::class, [SendTwoFactorSecurityNotice::class, 'reset']);
        Event::listen(RecoveryCodeUsed::class, [SendTwoFactorSecurityNotice::class, 'recoveryCodeUsed']);
        Event::listen(RecoveryCodesRegenerated::class, [SendTwoFactorSecurityNotice::class, 'recoveryCodesRegenerated']);
    }
}
