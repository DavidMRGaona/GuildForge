<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Http\Middleware\Concerns\ChecksTheImpersonatorsSession;
use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Filament's session check (panel middleware, run again by every Livewire request as
 * persistent middleware) aware of impersonation. Bound in place of the parent: Livewire
 * picks persistent middleware by the parent's class name, so the panel keeps naming it.
 */
final class AuthenticatePanelImpersonatorSession extends AuthenticateSession
{
    use ChecksTheImpersonatorsSession;

    public function __construct(
        AuthFactory $auth,
        private readonly ImpersonationContextInterface $impersonation,
    ) {
        parent::__construct($auth);
    }
}
