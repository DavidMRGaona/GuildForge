<?php

declare(strict_types=1);

namespace Tests\Support\Modules;

use App\Application\Modules\Services\ModuleChangeWindowInterface;
use App\Domain\Modules\ValueObjects\ModuleSetFingerprint;
use Closure;

/**
 * A module change window that only counts: for services built by hand in tests, which need to
 * know whether a step ran inside the window without a cache or an enabled-modules resolver.
 */
final class RecordingModuleChangeWindow implements ModuleChangeWindowInterface
{
    public int $depth = 0;

    public int $opened = 0;

    public int $closed = 0;

    public function run(Closure $change): mixed
    {
        $this->open();

        try {
            return $change();
        } finally {
            $this->close();
        }
    }

    public function open(): void
    {
        $this->depth++;
        $this->opened++;
    }

    public function close(): void
    {
        if ($this->depth === 0) {
            return;
        }

        $this->depth--;
        $this->closed++;
    }

    public function inProgress(): bool
    {
        return $this->depth > 0;
    }

    public function currentFingerprint(): ?ModuleSetFingerprint
    {
        return null;
    }
}
