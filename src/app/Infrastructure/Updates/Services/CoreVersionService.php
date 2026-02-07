<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

final class CoreVersionService implements CoreVersionServiceInterface
{
    private ?ModuleVersion $cachedVersion = null;

    public function getCurrentVersion(): ModuleVersion
    {
        if ($this->cachedVersion !== null) {
            return $this->cachedVersion;
        }

        $versionFile = base_path('VERSION');

        if (! File::exists($versionFile)) {
            // Fallback to default version
            $this->cachedVersion = ModuleVersion::fromString('0.0.0');

            return $this->cachedVersion;
        }

        $version = trim(File::get($versionFile));
        $this->cachedVersion = ModuleVersion::fromString($version);

        return $this->cachedVersion;
    }

    public function getCurrentCommit(): string
    {
        // Try git from base_path (works if .git is here or in a parent accessible to git)
        if (is_dir(base_path('.git'))) {
            return $this->runGitRevParse(base_path()) ?? 'unknown';
        }

        // Try parent directory (app lives in src/ subdirectory, .git is at repo root)
        $parentDir = dirname(base_path());

        if (is_dir($parentDir . '/.git')) {
            return $this->runGitRevParse($parentDir) ?? 'unknown';
        }

        // Fallback: environment variable (useful for Docker/CI where .git is not mounted)
        $envCommit = env('GIT_COMMIT');

        if (is_string($envCommit) && $envCommit !== '') {
            return $envCommit;
        }

        return 'unknown';
    }

    private function runGitRevParse(string $directory): ?string
    {
        $process = new Process(['git', 'rev-parse', 'HEAD']);
        $process->setWorkingDirectory($directory);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }

    public function satisfies(string $constraint): bool
    {
        return $this->getCurrentVersion()->satisfies($constraint);
    }
}
