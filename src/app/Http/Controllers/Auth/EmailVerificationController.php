<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    /**
     * An account that has already verified has nothing to do here: back to the page it asked for.
     */
    public function notice(Request $request): Response|RedirectResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('home'));
        }

        // Where the link went: a pending address replaces the current one (see resend())
        return Inertia::render('Auth/VerifyEmail', [
            'email' => $user->email,
            'pendingEmail' => $user->pending_email,
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.verify_email'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        if (! $this->isSignedInUser($request, $id) || ! $this->authService->verifyEmail($id, $hash)) {
            return redirect()->route('verification.notice')
                ->with('error', __('auth.verification_failed'));
        }

        // Back to the page EnsureEmailIsVerifiedWhenRequired stopped, when there was one
        return redirect()->intended(route('home'))
            ->with('success', __('auth.email_verified'));
    }

    public function resend(Request $request): RedirectResponse
    {
        /** @var UserModel $user */
        $user = $request->user();
        $userId = (string) $user->id;

        if ($this->authService->hasVerifiedEmail($userId)) {
            return redirect()->route('home');
        }

        // With an email change pending, the current address is the one the user is replacing
        // (often a typo): send the link that confirms the new one, which also verifies the account
        if (! $this->authService->sendPendingEmailVerificationNotification($userId)) {
            $this->authService->sendEmailVerificationNotification($userId);
        }

        return back()->with('success', __('auth.verification_link_sent'));
    }

    public function verifyPendingEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        if (! $this->isSignedInUser($request, $id) || ! $this->authService->verifyPendingEmail($id, $hash)) {
            return redirect()->route('profile.show')
                ->with('error', __('auth.email_change_verification_failed'));
        }

        return redirect()->route('profile.show')
            ->with('success', __('auth.email_changed'));
    }

    /**
     * A signed link is only valid for the account it was sent to, never for whoever is signed in.
     */
    private function isSignedInUser(Request $request, string $id): bool
    {
        /** @var UserModel $user */
        $user = $request->user();

        return hash_equals((string) $user->getKey(), $id);
    }
}
