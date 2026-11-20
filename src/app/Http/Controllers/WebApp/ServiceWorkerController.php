<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp;

use App\Application\WebApp\Services\ServiceWorkerScriptInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The service worker at `/sw.js` (core#802). Its URL is fixed, since the worker's scope is the root;
 * revalidated on every check (`no-cache`, with an ETag), so a deploy reaches browsers at once.
 * Never named by its hash like the other scripts (core#794): the browser compares the content.
 */
final class ServiceWorkerController extends Controller
{
    public function __construct(
        private readonly ServiceWorkerScriptInterface $script,
    ) {}

    public function __invoke(Request $request): Response
    {
        $script = $this->script->script();

        if ($script === null) {
            abort(404);
        }

        $response = response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setPublic();
        $response->headers->addCacheControlDirective('no-cache');
        $response->setEtag(hash('sha256', $script));
        $response->isNotModified($request);

        return $response;
    }
}
