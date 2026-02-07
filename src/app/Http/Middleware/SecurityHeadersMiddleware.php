<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
        $response->headers->set('Content-Security-Policy', $this->buildCsp());

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function buildCsp(): string
    {
        $directives = [
            "default-src 'self'",
            $this->scriptSrc(),
            $this->styleSrc(),
            "img-src 'self' https://res.cloudinary.com data:",
            "font-src 'self'",
            $this->connectSrc(),
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }

    /**
     * Livewire and Alpine.js (Filament) require unsafe-inline and unsafe-eval.
     */
    private function scriptSrc(): string
    {
        $sources = "'self' 'unsafe-inline' 'unsafe-eval'";

        if (app()->environment('local')) {
            $sources .= ' http://localhost:5173';
        }

        return "script-src {$sources}";
    }

    /**
     * Tailwind and Filament use inline styles.
     */
    private function styleSrc(): string
    {
        $sources = "'self' 'unsafe-inline'";

        if (app()->environment('local')) {
            $sources .= ' http://localhost:5173';
        }

        return "style-src {$sources}";
    }

    private function connectSrc(): string
    {
        $sources = "'self'";

        if (app()->environment('local')) {
            $sources .= ' http://localhost:5173 ws://localhost:5173';
        }

        return "connect-src {$sources}";
    }
}
