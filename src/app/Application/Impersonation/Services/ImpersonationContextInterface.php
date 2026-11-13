<?php

declare(strict_types=1);

namespace App\Application\Impersonation\Services;

use App\Domain\Impersonation\Enums\ImpersonationMode;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Whether the current request runs as another account because an administrator is
 * impersonating it. Public contract for modules: one that does something outside the
 * database during the request (calling an external API when saving, sending a test message)
 * asks isActive() and does not do it.
 *
 * Active only once the request has checked the impersonation again (ApplyImpersonation, on
 * every site and panel request, Livewire included); never in queued jobs or console commands.
 * While it is active, the guard's user is the target: impersonator() is the account the
 * session really belongs to.
 */
interface ImpersonationContextInterface
{
    public function isActive(): bool;

    /** The administrator who is impersonating; null when no impersonation is active */
    public function impersonator(): ?Authenticatable;

    /** The account being impersonated; null when no impersonation is active */
    public function target(): ?Authenticatable;

    /** null when no impersonation is active */
    public function mode(): ?ImpersonationMode;
}
