<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\DTOs\CreateUserDTO;
use App\Application\Services\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class RegisterController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
    ) {
    }

    public function create(): Response
    {
        return Inertia::render('Auth/Register');
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

        return redirect()->intended(route('home'))
            ->with('success', __('auth.registered'));
    }
}
