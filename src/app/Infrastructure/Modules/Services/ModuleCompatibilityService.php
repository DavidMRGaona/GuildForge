<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use App\Application\Modules\Services\HostEnvironmentProviderInterface;
use App\Application\Modules\Services\ModuleCompatibilityChecker;
use App\Application\Modules\Services\ModuleCompatibilityServiceInterface;
use App\Domain\Modules\Enums\RequirementType;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Modules\ValueObjects\CompatibilityResult;
use App\Domain\Modules\ValueObjects\ModuleName;
use InvalidArgumentException;
use TypeError;

final class ModuleCompatibilityService implements ModuleCompatibilityServiceInterface
{
    /** @var array<string, CompatibilityResult> Keyed by "path|mtime|size", so a replaced module is evaluated again */
    private array $installed = [];

    public function __construct(
        private readonly ModuleManifestReader $reader,
        private readonly ModuleCompatibilityChecker $checker,
        private readonly HostEnvironmentProviderInterface $host,
    ) {
    }

    public function checkInstalled(ModuleName $name): CompatibilityResult
    {
        $path = rtrim((string) config('modules.path', base_path('modules')), '/')."/{$name->value}/module.json";
        clearstatcache(true, $path);

        $key = is_file($path)
            ? $path.'|'.(int) @filemtime($path).'|'.(int) @filesize($path)
            : $path.'|missing';

        return $this->installed[$key] ??= $this->checkManifestFile($path);
    }

    public function checkManifestFile(string $manifestPath): CompatibilityResult
    {
        if (! is_file($manifestPath)) {
            return self::manifestProblem(CompatibilityIssue::MANIFEST_MISSING);
        }

        try {
            $manifest = $this->reader->read($manifestPath);
        } catch (InvalidArgumentException|TypeError) {
            return self::manifestProblem(CompatibilityIssue::MANIFEST_INVALID);
        }

        return $this->checkManifest($manifest);
    }

    public function checkManifest(ModuleManifestDTO $manifest): CompatibilityResult
    {
        return $this->checker->checkManifest($manifest, $this->host->current());
    }

    private static function manifestProblem(string $reasonKey): CompatibilityResult
    {
        return new CompatibilityResult([new CompatibilityIssue(RequirementType::Manifest, '', null, $reasonKey)]);
    }
}
