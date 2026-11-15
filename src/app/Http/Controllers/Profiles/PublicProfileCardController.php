<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profiles;

use App\Application\Profiles\DTOs\ProfileCardDTO;
use App\Application\Profiles\DTOs\PublicProfileDTO;
use App\Application\Profiles\Services\ProfileCardServiceInterface;
use App\Application\Profiles\Services\PublicProfileServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The summary card of a public profile, `GET /miembros/{handle}/tarjeta` (core#744), as JSON. Whoever
 * may not see the profile gets the 404 the profile page answers. An old address, or the current one
 * written differently, answers the card of the current one rather than a redirect: the card names its
 * address (`handle`, `url`), a client fetching JSON then needs one request (counted once by the limit)
 * and a permanent redirect that depends on who asks is never left for a cache to keep. Like the
 * page's redirect, it happens only once the access rule let this person see the profile.
 */
final class PublicProfileCardController extends Controller
{
    /**
     * Resolved per call, never kept by a controller instance: the lookup and the card share the
     * request's profile rows (bound scoped).
     */
    public function __invoke(Request $request, string $handle, PublicProfileServiceInterface $profiles, ProfileCardServiceInterface $cards): JsonResponse
    {
        $viewer = $request->user();
        $lookup = $profiles->findByHandle($handle, $viewer);

        if ($lookup->movedTo !== null) {
            $lookup = $profiles->findByHandle($lookup->movedTo, $viewer);
        }

        $card = $lookup->profile instanceof PublicProfileDTO ? $cards->card($lookup->profile->userId, $viewer) : null;

        abort_unless($card instanceof ProfileCardDTO, 404);

        // What it holds depends on who asks: never kept by a shared cache
        return response()->json($card->toArray())->header('Cache-Control', 'no-store, private');
    }
}
