<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp;

use App\Application\WebApp\Services\WebAppManifestInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The web app manifest at `/manifest.webmanifest` (core#802): the same for everybody, so it runs
 * without a session and carries nothing about the account. Revalidated on every use, since the
 * name, colours and favicon can change from the panel.
 */
final class WebAppManifestController extends Controller
{
    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(
        private readonly WebAppManifestInterface $manifest,
    ) {}

    public function __invoke(Request $request): Response
    {
        $body = json_encode($this->manifest->manifest()->toArray(), self::JSON_FLAGS);

        $response = response($body, 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setPublic();
        $response->headers->addCacheControlDirective('no-cache');
        $response->setEtag(hash('sha256', $body));
        $response->isNotModified($request);

        return $response;
    }
}
