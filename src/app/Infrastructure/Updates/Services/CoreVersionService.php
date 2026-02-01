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
        $process = new Process(['git', 'rev-parse', 'HEAD']);
        $process->setWorkingDirectory(base_path());
        $process->run();

        if (! $process->isSuccessful()) {
            return 'unknown';
        }

        return trim($process->getOutput());
    }

    public function satisfies(string $constraint): bool
    {
        return $this->getCurrentVersion()->satisfies($constraint);
    }
}
