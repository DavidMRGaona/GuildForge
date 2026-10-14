<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\DTOs\CreateUserDTO;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\AuthServiceInterface;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class RegisterController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly SeoMetadataFactoryInterface $seo,
        private readonly EmailVerificationPolicyInterface $emailVerification,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.register'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string} $validated */
        $validated = $request->validated();

        $dto = CreateUserDTO::fromArray($validated);
        $this->authService->register($dto);

        $this->authService->attemptLogin(
            $validated['email'],
            $validated['password'],
        );

        $request->session()->regenerate();

        // Straight to the notice: any page asked for stays as "intended" until the email is verified
        $user = $request->user();
        if ($user !== null && $this->emailVerification->mustVerify($user)) {
            return redirect()->route('verification.notice')
                ->with('success', __('auth.registered'));
        }

        return redirect()->intended(route('home'))
            ->with('success', __('auth.registered'));
    }
}
