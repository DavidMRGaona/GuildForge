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
 * Fails closed: a declared page that cannot be used (missing, not a page, not registered in the
 * panel, outside the cluster, or a provider that throws) still counts as declared, so the generic
 * page never takes over settings the module meant to guard with its own permission.
 */
final readonly class ModuleSettingsPages
{
    public function __construct(
        private ModuleLoader $loader,
        private LoggerInterface $logger,
    ) {}

    /**
     * Whether the module keeps its settings out of the generic page (usable page or not).
     */
    public function declaresPage(string $module): bool
    {
        return $this->declared($module) !== null;
    }

    /**
     * The module's declared page when the panel can open it; null when it declares none or the
     * declared one cannot be used.
     *
     * @return class-string<Page>|null
     */
    public function usablePage(string $module): ?string
    {
        $page = $this->declared($module);

        if ($page === null || $page === false) {
            return null;
        }

        $problem = $this->problemWith($page);

        if ($problem !== null) {
            $this->logger->warning('[ModuleSettingsPages] Declared settings page cannot be used: '.$problem, [
                'module' => $module,
                'page' => $page,
            ]);

            return null;
        }

        /** @var class-string<Page> $page */
        return $page;
    }

    /**
     * The class the provider declares, false when asking it failed, null when it declares none.
     */
    private function declared(string $module): string|false|null
    {
        $provider = $this->loader->getProvider($module);

        if (! $provider instanceof ModuleServiceProvider) {
            return null;
        }

        try {
            return $provider->getSettingsPage();
        } catch (Throwable $e) {
            $this->logger->warning('[ModuleSettingsPages] Could not read the declared settings page', [
                'module' => $module,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function problemWith(string $page): ?string
    {
        if (! class_exists($page) || ! is_subclass_of($page, Page::class)) {
            return 'not a Filament page';
        }

        if (! in_array($page, Filament::getPanel('admin')->getPages(), true)) {
            return 'not registered in the panel';
        }

        if ($page::getCluster() !== SettingsCluster::class) {
            return 'outside the settings cluster';
        }

        return null;
    }
}
