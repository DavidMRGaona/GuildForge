<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\TwoFactor\DTOs\PendingTwoFactorLoginDTO;
use App\Application\TwoFactor\Services\PendingTwoFactorLoginInterface;
use App\Application\TwoFactor\Services\TwoFactorChallengeInterface;
use App\Domain\TwoFactor\TwoFactorFailure;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The site's second step of signing in (core#770). Without a pending sign-in (none, expired or
 * dropped) it sends back to the login, saying the same whether the account exists or not.
 */
final class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly PendingTwoFactorLoginInterface $pendingLogins,
        private readonly TwoFactorChallengeInterface $challenge,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->intended(route('home'));
        }

        if (! $this->pendingLogins->current($request->session()) instanceof PendingTwoFactorLoginDTO) {
            return $this->signInAgain();
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.two_factor_challenge'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    public function store(TwoFactorChallengeRequest $request): RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->intended(route('home'));
        }

        $session = $request->session();
        $pending = $this->pendingLogins->current($session);

        if (! $pending instanceof PendingTwoFactorLoginDTO) {
            return $this->signInAgain();
        }

        $check = $this->challenge->check($pending->userId, $request->code(), $request->usesRecoveryCode());

        if ($check->passed) {
            $this->pendingLogins->forget($session);
            $this->challenge->signIn($session, $pending->userId, $pending->remember);

            $redirect = redirect()->intended(route('home'));

            return $check->recoveryCodesRemaining === null
                ? $redirect
                : $redirect->with('warning', trans_choice('auth.two_factor.recovery_codes_left', $check->recoveryCodesRemaining, [
                    'count' => $check->recoveryCodesRemaining,
                ]));
        }

        if ($check->failure === TwoFactorFailure::Unavailable) {
            throw ValidationException::withMessages(['code' => [__('auth.two_factor.unavailable')]]);
        }

        if (! $this->pendingLogins->recordFailure($session)) {
            return redirect()->route('login')->with('warning', __('auth.two_factor.too_many_failures'));
        }

        throw ValidationException::withMessages(['code' => [__('auth.two_factor.invalid_code')]]);
    }

    private function signInAgain(): RedirectResponse
    {
        return redirect()->route('login')->with('warning', __('auth.two_factor.sign_in_again'));
    }
}
