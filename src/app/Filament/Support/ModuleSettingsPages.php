<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Filament\Clusters\SettingsCluster;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Where a module's settings are edited (core#666): the page it declares with
 * ModuleServiceProvider::getSettingsPage(), in the «Configuración» cluster and guarded by a
 * permission of the module, or, only for modules that declare none, the generic
 * ModuleSettingsPage with their getSettingsSchema().
 *
 * Fails closed: a declared page that cannot be used (not a page, not one of the module's own
 * pages, not registered in the panel, outside the cluster, or a provider or check that throws)
 * still counts as declared, so the generic page never takes over settings the module meant to
 * guard with its own permission.
 *
 * Bound scoped: what a module declares is worked out once per request (the modules page asks
 * for every row on every render), so each problem is logged once. Access checks are asked on
 * every call, since they depend on who is signed in; only a check that throws is remembered.
 */
final class ModuleSettingsPages
{
    /** @var array<string, array{declared: bool, page: class-string<Page>|null, url: string|null}> */
    private array $resolved = [];

    /** @var array<string, true> */
    private array $failedAccessChecks = [];

    public function __construct(
        private readonly ModuleLoader $loader,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Whether the module keeps its settings out of the generic page (usable page or not).
     */
    public function declaresPage(string $module): bool
    {
        return $this->resolve($module)['declared'];
    }

    /**
     * The module's declared page when the panel can open it; null when it declares none or the
     * declared one cannot be used.
     *
     * @return class-string<Page>|null
     */
    public function usablePage(string $module): ?string
    {
        return $this->resolve($module)['page'];
    }

    /**
     * The URL of the module's usable page, whoever asks.
     */
    public function pageUrl(string $module): ?string
    {
        return $this->resolve($module)['url'];
    }

    /**
     * The URL of the module's usable page when the signed-in user may open it, so a link never
     * ends in a 403. An access check that throws offers no URL (logged once per request).
     */
    public function accessibleUrl(string $module): ?string
    {
        ['page' => $page, 'url' => $url] = $this->resolve($module);

        if ($page === null || $url === null || isset($this->failedAccessChecks[$module])) {
            return null;
        }

        try {
            return $page::canAccess() ? $url : null;
        } catch (Throwable $e) {
            $this->failedAccessChecks[$module] = true;
            $this->warn($module, $page, 'access_check_failed', $e);

            return null;
        }
    }

    /**
     * @return array{declared: bool, page: class-string<Page>|null, url: string|null}
     */
    private function resolve(string $module): array
    {
        return $this->resolved[$module] ??= $this->work($module);
    }

    /**
     * @return array{declared: bool, page: class-string<Page>|null, url: string|null}
     */
    private function work(string $module): array
    {
        $provider = $this->loader->getProvider($module);

        if (! $provider instanceof ModuleServiceProvider) {
            return ['declared' => false, 'page' => null, 'url' => null];
        }

        try {
            $page = $provider->getSettingsPage();
        } catch (Throwable $e) {
            $this->warn($module, null, 'provider_failed', $e);

            return ['declared' => true, 'page' => null, 'url' => null];
        }

        if ($page === null) {
            return ['declared' => false, 'page' => null, 'url' => null];
        }

        try {
            $problem = $this->problemWith($provider, $page);
            $url = $problem === null ? $page::getUrl() : null;
        } catch (Throwable $e) {
            $this->warn($module, $page, 'check_failed', $e);

            return ['declared' => true, 'page' => null, 'url' => null];
        }

        if ($problem !== null) {
            $this->warn($module, $page, $problem);

            return ['declared' => true, 'page' => null, 'url' => null];
        }

        return ['declared' => true, 'page' => $page, 'url' => $url];
    }

    /**
     * Why the declared page cannot be used, or null when it can.
     */
    private function problemWith(ModuleServiceProvider $provider, string $page): ?string
    {
        if (! class_exists($page) || ! is_subclass_of($page, Page::class)) {
            return 'not_a_page';
        }

        // Its own page: never another module's or the core's, whose permission it does not decide
        if (! in_array($page, $provider->registerFilamentPages(), true)) {
            return 'not_listed_by_module';
        }

        if (! in_array($page, Filament::getPanel('admin')->getPages(), true)) {
            return 'not_registered_in_panel';
        }

        if ($page::getCluster() !== SettingsCluster::class) {
            return 'outside_settings_cluster';
        }

        return null;
    }

    private function warn(string $module, ?string $page, string $reason, ?Throwable $error = null): void
    {
        $this->logger->warning('[ModuleSettingsPages] The declared settings page of a module cannot be used', array_filter([
            'module' => $module,
            'page' => $page,
            'reason' => $reason,
            'error' => $error?->getMessage(),
        ], fn (?string $value): bool => $value !== null));
    }
}
