<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Services\AuthServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
    ) {
    }

    public function notice(): Response
    {
        return Inertia::render('Auth/VerifyEmail');
    }

    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        if (! $this->authService->verifyEmail($id, $hash)) {
            return redirect()->route('verification.notice')
                ->with('error', __('auth.verification_failed'));
        }

        return redirect()->route('home')
            ->with('success', __('auth.email_verified'));
    }

    public function resend(Request $request): RedirectResponse
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $user */
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
