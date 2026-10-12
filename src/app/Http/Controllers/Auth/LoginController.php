<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class LoginController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.login'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (! $this->authService->attemptLogin(
            $credentials['email'],
            $credentials['password'],
            $credentials['remember'] ?? false,
        )) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authService->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
