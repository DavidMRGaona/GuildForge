<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Mail\DTOs\Response\MailLogoPngDTO;
use App\Application\Mail\Services\MailLogoServiceInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The SVG mail logo as a PNG (`/correos/logo.png`), which mails link instead of the SVG that
 * Gmail and Outlook do not show. It only ever serves the configured logo: no parameter picks
 * a file or a size. The `v` of the current logo is cached for good (a new logo gets a new
 * `v`); any other answers the current logo for a short while.
 */
final class MailLogoController extends Controller
{
    private const int CURRENT_VERSION_MAX_AGE = 365 * 24 * 3600;

    private const int OTHER_VERSION_MAX_AGE = 300;

    public function __construct(
        private readonly MailLogoServiceInterface $logo,
    ) {}

    public function __invoke(Request $request): Response
    {
        $png = $this->logo->png();

        if (! $png instanceof MailLogoPngDTO) {
            abort(404);
        }

        $response = response($png->bytes, 200, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);

        $response->setPublic();
        $response->setEtag($png->version);

        if ($request->query('v') === $png->version) {
            $response->setMaxAge(self::CURRENT_VERSION_MAX_AGE);
            $response->setImmutable();
        } else {
            $response->setMaxAge(self::OTHER_VERSION_MAX_AGE);
        }

        $response->isNotModified($request);

        return $response;
    }
}
