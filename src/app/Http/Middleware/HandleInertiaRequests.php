<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Modules\Services\ModulePageRegistryInterface;
use App\Application\Modules\Services\ModuleSlotRegistryInterface;
use App\Application\Navigation\Services\MenuServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;
use App\Modules\ModuleLoader;
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
            'favicons' => fn () => $this->getFavicons(),
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
            'moduleSlots' => fn () => $this->getModuleSlots(),
            'modulePages' => fn () => $this->getModulePages(),
            'moduleTranslations' => fn () => $this->getModuleTranslations(),
            'navigation' => fn () => $this->getNavigation($request),
        ];
    }

    private function getSiteLogo(string $key): string
    {
        $defaultLogos = [
            'site_logo_light' => '/images/defaults/logo-light.png',
            'site_logo_dark' => '/images/defaults/logo-dark.png',
        ];

        try {
            $settingsService = app(SettingsServiceInterface::class);
            $logoPath = (string) $settingsService->get($key, '');

            if ($logoPath === '') {
                return $defaultLogos[$key] ?? '';
            }

            return Storage::disk('images')->url($logoPath);
        } catch (\Throwable $e) {
            $this->logDebugError('getSiteLogo', $e);

            return $defaultLogos[$key] ?? '';
        }
    }

    /**
     * Get favicon URLs for frontend.
     *
     * Returns custom favicon URLs if configured, or null for defaults.
     *
     * @return array{light: string|null, dark: string|null}
     */
    private function getFavicons(): array
    {
        try {
            $settingsService = app(SettingsServiceInterface::class);

            $lightPath = (string) $settingsService->get('site_favicon_light', '');
            $darkPath = (string) $settingsService->get('site_favicon_dark', '');

            return [
                'light' => $lightPath !== '' ? Storage::disk('images')->url($lightPath) : null,
                'dark' => $darkPath !== '' ? Storage::disk('images')->url($darkPath) : null,
            ];
        } catch (\Throwable $e) {
            $this->logDebugError('getFavicons', $e);

            return [
                'light' => null,
                'dark' => null,
            ];
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
        } catch (\Throwable $e) {
            $this->logDebugError('getThemeSettings', $e);

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

            // Get roles and permissions from authorization service
            $authService = app(AuthorizationServiceInterface::class);
            $roles = $authService->getRoles($user);
            $permissions = $authService->getPermissions($user);

            return [
                'id' => $userDTO->id,
                'name' => $userDTO->name,
                'displayName' => $userDTO->displayName,
                'email' => $userDTO->email,
                'avatarPublicId' => $userDTO->avatarPublicId,
                'role' => $userDTO->role,
                'emailVerified' => $userDTO->emailVerified,
                'createdAt' => $userDTO->createdAt->format('c'),
                // New authorization fields
                'roles' => $roles,
                'permissions' => $permissions,
            ];
        } catch (\Throwable $e) {
            $this->logDebugError('getAuthUser', $e);

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
        } catch (\Throwable $e) {
            $this->logDebugError('getAuthSettings', $e);

            // Return safe defaults if service fails
            return [
                'registrationEnabled' => true,
                'loginEnabled' => true,
                'emailVerificationRequired' => false,
            ];
        }
    }

    /**
     * Get module slots for frontend.
     *
     * @return array<string, array<array<string, mixed>>>
     */
    private function getModuleSlots(): array
    {
        try {
            $slotRegistry = app(ModuleSlotRegistryInterface::class);

            return $slotRegistry->toInertiaPayload();
        } catch (\Throwable $e) {
            $this->logDebugError('getModuleSlots', $e);

            return [];
        }
    }

    /**
     * Get module page prefixes for frontend page resolution.
     *
     * @return array<string, string>
     */
    private function getModulePages(): array
    {
        try {
            $pageRegistry = app(ModulePageRegistryInterface::class);

            return $pageRegistry->toInertiaPayload();
        } catch (\Throwable $e) {
            $this->logDebugError('getModulePages', $e);

            return [];
        }
    }

    /**
     * Get module translations for frontend i18n.
     *
     * Loads translations from module JS locale files and shares them with the frontend.
     * This enables runtime translation loading for modules installed after the build.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function getModuleTranslations(): array
    {
        try {
            if (! app()->bound(ModuleLoader::class)) {
                return [];
            }

            $loader = app(ModuleLoader::class);
            $translations = [];

            foreach ($loader->getLoadedProviders() as $moduleName => $provider) {
                $modulePath = $provider->getModulePath();
                $localesPath = $modulePath.'/resources/js/locales';

                if (! is_dir($localesPath)) {
                    continue;
                }

                foreach (['es', 'en'] as $locale) {
                    $localeFile = $localesPath.'/'.$locale.'.ts';

                    if (! file_exists($localeFile)) {
                        continue;
                    }

                    $content = $this->parseTypeScriptLocaleFile($localeFile);

                    if ($content !== null) {
                        $translations[$moduleName][$locale] = $content;
                    }
                }
            }

            return $translations;
        } catch (\Throwable $e) {
            $this->logDebugError('getModuleTranslations', $e);

            return [];
        }
    }

    /**
     * Parse a TypeScript locale file and extract its default export.
     *
     * This is a simplified parser that handles the common format:
     * export default { key: 'value', ... }
     *
     * @return array<string, mixed>|null
     */
    private function parseTypeScriptLocaleFile(string $filePath): ?array
    {
        if (! file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        // Find the default export object
        // Pattern: export default { ... }
        if (! preg_match('/export\s+default\s+(\{[\s\S]*\})\s*;?\s*$/m', $content, $matches)) {
            return null;
        }

        $objectContent = $matches[1];

        // Convert TypeScript object literal to JSON
        // 1. Handle unquoted keys: key: -> "key":
        $json = preg_replace('/(\s*)([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', '$1"$2":', $objectContent);

        // 2. Handle single quotes -> double quotes (but not within double-quoted strings)
        if ($json !== null) {
            $json = $this->convertSingleToDoubleQuotes($json);
        }

        // 3. Remove trailing commas before } or ]
        if ($json !== null) {
            $json = preg_replace('/,(\s*[\}\]])/', '$1', $json);
        }

        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Convert single quotes to double quotes in a string, handling escapes.
     *
     * When converting single-quoted strings to double-quoted strings,
     * any double quotes inside the string must be escaped.
     */
    private function convertSingleToDoubleQuotes(string $content): string
    {
        $result = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $length = strlen($content);

        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];
            $prevChar = $i > 0 ? $content[$i - 1] : '';

            // Handle escape sequences
            if ($prevChar === '\\') {
                $result .= $char;

                continue;
            }

            // Toggle quote states and handle conversions
            if ($char === "'" && ! $inDoubleQuote) {
                $inSingleQuote = ! $inSingleQuote;
                $result .= '"';
            } elseif ($char === '"') {
                if ($inSingleQuote) {
                    // Double quote inside a single-quoted string needs to be escaped
                    // because we're converting to double-quoted JSON
                    $result .= '\\"';
                } else {
                    $inDoubleQuote = ! $inDoubleQuote;
                    $result .= $char;
                }
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    /**
     * Get navigation menus for frontend.
     *
     * @return array{header: array<array<string, mixed>>, footer: array<array<string, mixed>>}
     */
    private function getNavigation(Request $request): array
    {
        try {
            $menuService = app(MenuServiceInterface::class);
            $user = $request->user();

            $headerItems = $menuService->getHeaderMenu($user);
            $footerItems = $menuService->getFooterMenu($user);

            return [
                'header' => array_map(fn ($item) => $item->toArray(), $headerItems),
                'footer' => array_map(fn ($item) => $item->toArray(), $footerItems),
            ];
        } catch (\Throwable $e) {
            $this->logDebugError('getNavigation', $e);

            return [
                'header' => [],
                'footer' => [],
            ];
        }
    }

    /**
     * Log an error in debug mode for troubleshooting.
     */
    private function logDebugError(string $method, \Throwable $e): void
    {
        if (config('app.debug')) {
            logger()->debug("[HandleInertiaRequests::{$method}] {$e->getMessage()}", [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}
