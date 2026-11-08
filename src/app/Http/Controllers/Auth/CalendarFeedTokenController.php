<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Calendar\Services\CalendarFeedTokenServiceInterface;
use App\Application\Calendar\Services\CalendarSubscriptionLinksFactoryInterface;
use App\Application\Calendar\Services\PersonalCalendarServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Issues and revokes the signed-in person's personal calendar address from the
 * profile. The address is only ever in the response to the issuing request: it is
 * not stored (only its hash is), so the profile cannot show it again.
 */
final class CalendarFeedTokenController extends Controller
{
    public function __construct(
        private readonly CalendarFeedTokenServiceInterface $tokens,
        private readonly PersonalCalendarServiceInterface $personalCalendar,
        private readonly TimezoneServiceInterface $timezone,
        private readonly SiteBrandingServiceInterface $branding,
        private readonly CalendarSubscriptionLinksFactoryInterface $links,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if (! $this->personalCalendar->isAvailable()) {
            abort(404);
        }

        /** @var UserModel $user */
        $user = $request->user();

        $token = $this->tokens->issue($user->id);
        $links = $this->links->for(
            route('calendar.personal.ics', ['token' => $token]),
            (string) __('calendar.personal.feed_name', ['site' => $this->branding->siteName()]),
        );

        return response()->json([
            ...$links->toArray(),
            'issuedAt' => $this->timezone->iso($this->tokens->issuedAt($user->id)),
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function destroy(Request $request): Response
    {
        /** @var UserModel $user */
        $user = $request->user();

        $this->tokens->revoke($user->id);

        return response()->noContent();
    }
}
