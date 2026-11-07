<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Calendar\Services\CalendarFeedTokenServiceInterface;
use App\Application\Calendar\Services\ICalendarWriterInterface;
use App\Application\Calendar\Services\PersonalCalendarServiceInterface;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

/**
 * A person's private subscription feed (`/calendario/personal/{token}.ics`): the
 * public calendar entries they take part in, from 30 days back to 12 months ahead.
 *
 * The token is the only credential, so every refusal looks the same (404): an
 * unknown or revoked token, a deactivated or anonymized account, or one that must
 * verify its email.
 */
final class PersonalCalendarFeedController extends Controller
{
    private const string PAST_WINDOW = '-30 days';

    private const string FUTURE_WINDOW = '+12 months';

    public function __construct(
        private readonly CalendarFeedTokenServiceInterface $tokens,
        private readonly PersonalCalendarServiceInterface $personalCalendar,
        private readonly ICalendarWriterInterface $writer,
        private readonly EmailVerificationPolicyInterface $emailVerification,
        private readonly SiteBrandingServiceInterface $branding,
        private readonly TimezoneServiceInterface $timezone,
    ) {}

    public function __invoke(string $token): Response
    {
        $user = $this->owner($token);

        if (! $user instanceof UserModel) {
            abort(404);
        }

        $now = $this->timezone->toStorage(CarbonImmutable::now());
        $entries = $this->personalCalendar->entriesFor(
            $user->id,
            $now->modify(self::PAST_WINDOW),
            $now->modify(self::FUTURE_WINDOW),
        );

        $name = (string) __('calendar.personal.feed_name', ['site' => $this->branding->siteName()]);

        return response($this->writer->write($entries, $name), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename=calendar.ics',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * The account the token belongs to, while it may still have a personal calendar.
     */
    private function owner(string $token): ?UserModel
    {
        $userId = $this->tokens->userIdFor($token);

        if ($userId === null) {
            return null;
        }

        // Soft-deleted (deactivated) accounts are excluded by the default scope
        $user = UserModel::query()->find($userId);

        if (! $user instanceof UserModel
            || $user->anonymized_at !== null
            || $this->emailVerification->mustVerify($user)) {
            return null;
        }

        return $user;
    }
}
