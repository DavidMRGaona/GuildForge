<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `X-Robots-Tag: noindex, nofollow` on every answer of the route: the page, its redirects and its 404,
 * so a page kept out of search engines answers alike whether it exists or not (core#741).
 */
final class KeepOutOfSearchEngines
{
    public const string VALUE = 'noindex, nofollow';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', self::VALUE);

        return $response;
    }
}
