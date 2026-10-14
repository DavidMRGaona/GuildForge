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

        return Inertia::render('Auth/VerifyEmail', [
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.verify_email'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        if (! $this->authService->verifyEmail($id, $hash)) {
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

        $this->authService->sendEmailVerificationNotification($userId);

        return back()->with('success', __('auth.verification_link_sent'));
    }

    public function verifyPendingEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        if (! $this->authService->verifyPendingEmail($id, $hash)) {
            return redirect()->route('profile.show')
                ->with('error', __('auth.email_change_verification_failed'));
        }

        return redirect()->route('profile.show')
            ->with('success', __('auth.email_changed'));
    }
}
