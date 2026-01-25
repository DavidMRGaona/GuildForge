<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Services\SettingsServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureLoginIsEnabled
{
    public function __construct(
        private SettingsServiceInterface $settings,
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->isLoginEnabled()) {
            return redirect('/');
        }

        return $next($request);
    }
}
