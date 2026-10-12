<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ResetPasswordController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.reset_password'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->authService->resetPassword(
            $validated['token'],
            $validated['email'],
            $validated['password'],
        );

        return redirect()->route('login')
            ->with('success', __('passwords.reset'));
    }
}
