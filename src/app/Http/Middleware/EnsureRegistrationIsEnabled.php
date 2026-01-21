<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Services\SettingsServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureRegistrationIsEnabled
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
        if (!$this->settings->isRegistrationEnabled()) {
            return redirect('/');
        }

        return $next($request);
    }
}
