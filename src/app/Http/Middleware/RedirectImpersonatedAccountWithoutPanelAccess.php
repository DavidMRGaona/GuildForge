<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Impersonation\Services\ImpersonationContextInterface;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * In the panel, after ApplyImpersonation. An impersonated account without admin.access would get
 * the panel's 403 with no band and no way back: it goes to the site, which has the band, with a
 * notice naming the account. Fails closed: when the check throws, the request goes on and the
 * panel's own access check refuses it as for any account without access.
 */
final readonly class RedirectImpersonatedAccountWithoutPanelAccess
{
    public function __construct(
        private ImpersonationContextInterface $context,
        private AuthorizationServiceInterface $authorization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $target = $this->context->target();

        if (! $this->context->isActive() || ! $target instanceof Authenticatable) {
            return $next($request);
        }

        try {
            if ($this->authorization->can($target, 'admin.access')) {
                return $next($request);
            }
        } catch (Throwable) {
            return $next($request);
        }

        $display = $target->display_name ?? '';
        $name = is_string($display) && $display !== '' ? $display : (string) ($target->name ?? '');

        return redirect()
            ->route('home')
            ->with('warning', __('filament.impersonation.no_panel_access', ['name' => $name]));
    }
}
