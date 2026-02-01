<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Updates\DTOs\HealthCheckResultDTO;
use App\Application\Updates\Services\ModuleHealthCheckerInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

final readonly class ModuleHealthChecker implements ModuleHealthCheckerInterface
{
    public function __construct(
        private ModuleManagerServiceInterface $moduleManager,
    ) {}

    public function check(ModuleName $name): HealthCheckResultDTO
    {
        $errors = [];
        $warnings = [];

        $providerLoads = $this->checkProviderLoads($name);
        if (! $providerLoads) {
            $errors[] = 'Service provider failed to load';
        }

        $routesRespond = $this->checkRoutesRespond($name);
        if (! $routesRespond) {
            $warnings[] = 'Module routes may not be responding correctly';
        }

        $filamentRegisters = $this->checkFilamentResources($name);
        if (! $filamentRegisters) {
            $warnings[] = 'Filament resources may not be registered correctly';
        }

        return new HealthCheckResultDTO(
            providerLoads: $providerLoads,
            routesRespond: $routesRespond,
            filamentRegisters: $filamentRegisters,
            errors: $errors,
            warnings: $warnings,
        );
    }

    public function checkProviderLoads(ModuleName $name): bool
    {
        try {
            $module = $this->moduleManager->find($name);

            if ($module === null) {
                return false;
            }

            $providerClass = $module->namespace() . '\\' . $module->provider();

            if (! class_exists($providerClass)) {
                Log::warning("Module provider class not found: {$providerClass}");

                return false;
            }

            // Try to instantiate the provider (without registering)
            $provider = app()->make($providerClass, ['app' => app()]);

            return $provider !== null;
        } catch (\Throwable $e) {
            Log::error("Health check failed for module {$name->value}: Provider load error", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function checkRoutesRespond(ModuleName $name): bool
    {
        try {
            $module = $this->moduleManager->find($name);

            if ($module === null) {
                return false;
            }

            // Check if route files exist
            $webRoutePath = $module->path() . '/routes/web.php';
            $apiRoutePath = $module->path() . '/routes/api.php';

            $hasRoutes = file_exists($webRoutePath) || file_exists($apiRoutePath);

            if (! $hasRoutes) {
                // No routes to check - this is okay
                return true;
            }

            // Basic validation that route files are parseable
            if (file_exists($webRoutePath)) {
                $content = file_get_contents($webRoutePath);
                if ($content === false || ! $this->isValidPhp($webRoutePath)) {
                    return false;
                }
            }

            if (file_exists($apiRoutePath)) {
                $content = file_get_contents($apiRoutePath);
                if ($content === false || ! $this->isValidPhp($apiRoutePath)) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::error("Health check failed for module {$name->value}: Routes check error", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function checkFilamentResources(ModuleName $name): bool
    {
        try {
            $module = $this->moduleManager->find($name);

            if ($module === null) {
                return false;
            }

            $filamentPath = $module->path() . '/src/Filament';

            if (! is_dir($filamentPath)) {
                // No Filament resources - this is okay
                return true;
            }

            // Check Resources directory
            $resourcesPath = $filamentPath . '/Resources';
            if (is_dir($resourcesPath)) {
                $files = glob($resourcesPath . '/*Resource.php');
                if ($files !== false) {
                    foreach ($files as $file) {
                        if (! $this->isValidPhp($file)) {
                            return false;
                        }
                    }
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::error("Health check failed for module {$name->value}: Filament check error", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate PHP syntax using Symfony Process (prevents shell injection).
     */
    private function isValidPhp(string $filePath): bool
    {
        $process = new Process(['php', '-l', $filePath]);
        $process->run();

        return $process->isSuccessful();
    }
}
