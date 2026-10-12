<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ForgotPasswordController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {
    }

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.forgot_password'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->authService->sendPasswordResetLink($request->validated('email'));

        return back()->with('success', __('passwords.sent'));
    }
}
