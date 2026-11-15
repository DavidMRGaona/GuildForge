<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * A named rate limit that runs where the route lists it (core#744). Laravel moves `throttle` ahead of
 * the web group's own middleware (its middleware priority puts ThrottleRequests before
 * SubstituteBindings), so its 429 left without the group's headers or the route's `X-Robots-Tag`, and
 * during an impersonation it counted for the administrator and wrote to the real cache before the
 * safety net engaged. This wraps ThrottleRequests instead of extending it, so the priority never
 * moves it: it runs after ApplyImpersonation (it counts for the account being impersonated, inside
 * the net) and inside every middleware listed before it, whose headers the 429 then carries.
 *
 * Usage: `->middleware([KeepOutOfSearchEngines::class, ThrottleWhereListed::class.':<limiter>'])`.
 */
final readonly class ThrottleWhereListed
{
    public function __construct(
        private ThrottleRequests $throttle,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $limiter): Response
    {
        return $this->throttle->handle($request, $next, $limiter);
    }
}
