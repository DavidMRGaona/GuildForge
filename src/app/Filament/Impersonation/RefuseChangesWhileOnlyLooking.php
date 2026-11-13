<?php

declare(strict_types=1);

namespace App\Filament\Impersonation;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Impersonation\ImpersonatedName;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Livewire\ComponentHook;
use Throwable;

/**
 * «Solo mirar» in the panel (core#576): while an administrator impersonates in read-only mode,
 * every Livewire call that PanelReadOnlyRule does not allow is stopped before it runs (actions
 * of rows, headers, bulk selections and forms, saving and creating, a module's own methods),
 * with a notice instead. Registered for every Livewire component, so module pages are covered
 * without doing anything; the site public pages are Inertia, blocked on their own (core#574).
 *
 * In «Ejecutar y deshacer» it does nothing: the request runs and is rolled back (core#572).
 * When anything fails while deciding, the call is refused.
 */
final class RefuseChangesWhileOnlyLooking extends ComponentHook
{
    /**
     * @param  array<int|string, mixed>  $params
     */
    public function call(string $method, array $params, callable $returnEarly): void
    {
        $context = app(ImpersonationContextInterface::class);

        if (! $context->isActive() || $context->mode() !== ImpersonationMode::ReadOnly) {
            return;
        }

        try {
            if (app(PanelReadOnlyRule::class)->allows($this->component, $method, $params)) {
                return;
            }
        } catch (Throwable $exception) {
            Log::warning('Read-only impersonation check failed; the call is refused.', [
                'component' => $this->component::class,
                'method' => $method,
                'exception' => $exception::class,
            ]);
        }

        $returnEarly(null);
        $this->notifyRefused($context->target());
    }

    private function notifyRefused(?Authenticatable $target): void
    {
        Notification::make()
            ->title(__('filament.impersonation_read_only.refused', [
                'name' => ImpersonatedName::of($target),
            ]))
            ->warning()
            ->send();
    }
}
