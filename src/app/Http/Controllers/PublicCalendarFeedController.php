<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Calendar\Services\PublicCalendarFeedDocumentInterface;
use App\Infrastructure\Calendar\Feed\PublicCalendarFeedDocument;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public subscription feed (`/calendario.ics`): what a guest sees in
 * /calendario, for anyone to add to their calendar app without an account.
 *
 * The route runs without a session, so the answer never depends on who asks and
 * shared caches can keep it: the same document for everyone, public for a few
 * minutes, with an ETag so calendar services that ask again get a 304.
 */
final class PublicCalendarFeedController extends Controller
{
    public function __construct(
        private readonly PublicCalendarFeedDocumentInterface $document,
    ) {}

    public function __invoke(Request $request): Response
    {
        $contents = $this->document->contents();

        $response = response($contents, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename=calendar.ics',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);

        $response->setPublic();
        $response->setMaxAge(PublicCalendarFeedDocument::CACHE_SECONDS);
        $response->setEtag(hash('sha256', $contents));
        $response->isNotModified($request);

        return $response;
    }
}
