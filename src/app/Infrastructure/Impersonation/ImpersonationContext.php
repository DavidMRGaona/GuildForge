<?php

declare(strict_types=1);

namespace App\Infrastructure\Impersonation;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The impersonation of the current request, set by ApplyImpersonation once the checks pass
 * and cleared when it ends. Bound scoped: one per request or job.
 */
final class ImpersonationContext implements ImpersonationContextInterface
{
    private ?Authenticatable $impersonator = null;

    private ?Authenticatable $target = null;

    private ?ImpersonationMode $mode = null;

    private ?string $logId = null;

    public function activate(Authenticatable $impersonator, Authenticatable $target, ImpersonationMode $mode, string $logId): void
    {
        $this->impersonator = $impersonator;
        $this->target = $target;
        $this->mode = $mode;
        $this->logId = $logId;
    }

    public function clear(): void
    {
        $this->impersonator = null;
        $this->target = null;
        $this->mode = null;
        $this->logId = null;
    }

    public function isActive(): bool
    {
        return $this->impersonator instanceof Authenticatable && $this->target instanceof Authenticatable;
    }

    public function impersonator(): ?Authenticatable
    {
        return $this->impersonator;
    }

    public function target(): ?Authenticatable
    {
        return $this->target;
    }

    public function mode(): ?ImpersonationMode
    {
        return $this->mode;
    }

    /** The open log entry; null when no impersonation is active */
    public function logId(): ?string
    {
        return $this->logId;
    }
}
