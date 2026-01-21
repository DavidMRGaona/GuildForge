<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin', 'admin/*', 'livewire/*')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'appDescription' => config('app.description'),
            'siteLogoLight' => fn () => $this->getSiteLogo('site_logo_light'),
            'siteLogoDark' => fn () => $this->getSiteLogo('site_logo_dark'),
            'theme' => fn () => $this->getThemeSettings(),
            'auth' => [
                'user' => fn () => $this->getAuthUser($request),
            ],
            'authSettings' => fn () => $this->getAuthSettings(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }

    private function getSiteLogo(string $key): ?string
    {
        try {
            $settingsService = app(SettingsServiceInterface::class);
            $logoPath = (string) $settingsService->get($key, '');

            if ($logoPath === '') {
                return null;
            }

            return Storage::disk('images')->url($logoPath);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get theme settings for frontend.
     *
     * @return array{
     *     cssVariables: string,
     *     darkModeDefault: bool,
     *     darkModeToggleVisible: bool,
     *     fontHeading: string,
     *     fontBody: string
     * }
     */
    private function getThemeSettings(): array
    {
        try {
            $themeService = app(ThemeSettingsServiceInterface::class);
            $settings = $themeService->getThemeSettings();

            return [
                'cssVariables' => $themeService->getCssVariables(),
                'darkModeDefault' => $settings->darkModeDefault,
                'darkModeToggleVisible' => $settings->darkModeToggleVisible,
                'fontHeading' => $settings->fontHeading,
                'fontBody' => $settings->fontBody,
            ];
        } catch (\Throwable) {
            // Return safe defaults if service fails
            return [
                'cssVariables' => '',
                'darkModeDefault' => false,
                'darkModeToggleVisible' => true,
                'fontHeading' => 'Inter',
                'fontBody' => 'Inter',
            ];
        }
    }

    /**
     * Get the authenticated user as a DTO.
     *
     * @return array<string, mixed>|null
     */
    private function getAuthUser(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        try {
            $factory = app(ResponseDTOFactoryInterface::class);
            $userDTO = $factory->createUserDTO($user);

            return [
                'id' => $userDTO->id,
                'name' => $userDTO->name,
                'displayName' => $userDTO->displayName,
                'email' => $userDTO->email,
                'avatarPublicId' => $userDTO->avatarPublicId,
                'role' => $userDTO->role,
                'emailVerified' => $userDTO->emailVerified,
                'createdAt' => $userDTO->createdAt->format('c'),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get authentication settings for frontend.
     *
     * @return array{registrationEnabled: bool, loginEnabled: bool, emailVerificationRequired: bool}
     */
    private function getAuthSettings(): array
    {
        try {
            $settingsService = app(SettingsServiceInterface::class);

            return [
                'registrationEnabled' => $settingsService->isRegistrationEnabled(),
                'loginEnabled' => $settingsService->isLoginEnabled(),
                'emailVerificationRequired' => $settingsService->isEmailVerificationRequired(),
            ];
        } catch (\Throwable) {
            // Return safe defaults if service fails
            return [
                'registrationEnabled' => true,
                'loginEnabled' => true,
                'emailVerificationRequired' => false,
            ];
        }
    }
}
