<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Impersonation\Services\ImpersonationAccountsInterface;
use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Impersonation\Services\ImpersonationLoggerInterface;
use App\Application\Impersonation\Services\ImpersonationRolesInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Filament\Impersonation\RefuseChangesWhileOnlyLooking;
use App\Http\Middleware\AuthenticateImpersonatorSession;
use App\Http\Middleware\AuthenticatePanelImpersonatorSession;
use App\Infrastructure\Impersonation\EloquentImpersonationAccounts;
use App\Infrastructure\Impersonation\EloquentImpersonationRoles;
use App\Infrastructure\Impersonation\ImpersonationContext;
use App\Infrastructure\Impersonation\ImpersonationLogger;
use App\Infrastructure\Impersonation\ImpersonationService;
use App\Infrastructure\Impersonation\SafetyNet\ImpersonationSafetyNet;
use App\Infrastructure\Impersonation\SafetyNet\RolledBackTransaction;
use Filament\Http\Middleware\AuthenticateSession as FilamentAuthenticateSession;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * An administrator viewing the site and the panel as another account (core#569): the
 * identity changes per request in the administrator's own session, and nothing signs in.
 */
final class ImpersonationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ImpersonationContext::class);
        $this->app->scoped(ImpersonationContextInterface::class, static fn ($app): ImpersonationContext => $app->make(ImpersonationContext::class));
        $this->app->bind(ImpersonationServiceInterface::class, ImpersonationService::class);
        $this->app->bind(ImpersonationLoggerInterface::class, ImpersonationLogger::class);
        $this->app->bind(ImpersonationAccountsInterface::class, EloquentImpersonationAccounts::class);
        $this->app->bind(ImpersonationRolesInterface::class, EloquentImpersonationRoles::class);

        // Singletons, not scoped: once engaged the outputs stay neutralised until the process
        // ends, terminating middleware and defer() callbacks included
        $this->app->singleton(RolledBackTransaction::class);
        $this->app->singleton(ImpersonationSafetyNet::class);

        // Both session checks store the administrator's password hash, not the target's.
        // Bound under the framework names: the panel and Livewire's persistent middleware
        // name those classes, and the container builds them.
        $this->app->bind(AuthenticateSession::class, AuthenticateImpersonatorSession::class);
        $this->app->bind(FilamentAuthenticateSession::class, AuthenticatePanelImpersonatorSession::class);

        // «Solo mirar» in the panel: Livewire calls that run actions or save are refused (core#576).
        // Before any provider boots: Livewire wires its component hooks when it boots, and a hook
        // added later would only reach the next application (none, under PHP-FPM).
        $this->app->booting(static function (): void {
            Livewire::componentHook(RefuseChangesWhileOnlyLooking::class);
        });
    }
}
