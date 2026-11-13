<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Http\Middleware\Concerns\ChecksTheImpersonatorsSession;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Session\Middleware\AuthenticateSession;

/**
 * Laravel's session check (alias auth.session, and Livewire's persistent middleware) aware
 * of impersonation. Bound in place of the parent, so every use resolves to it.
 */
final class AuthenticateImpersonatorSession extends AuthenticateSession
{
    use ChecksTheImpersonatorsSession;

    public function __construct(
        AuthFactory $auth,
        private readonly ImpersonationContextInterface $impersonation,
    ) {
        parent::__construct($auth);
    }
}
