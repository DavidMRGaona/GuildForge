<?php

declare(strict_types=1);

namespace Tests\Support\Calendar;

use App\Application\Calendar\DTOs\CalendarEntryReferenceDTO;
use App\Application\Calendar\Services\CalendarAttendanceSourceRegistryInterface;
use App\Application\Services\SettingsServiceInterface;

/**
 * Helpers for feature tests of the personal calendar: an attendance source that
 * answers fixed references, and an account that must verify its email.
 */
trait RegistersPersonalCalendarSources
{
    /**
     * @param  array<string, array<CalendarEntryReferenceDTO>>  $referencesByUser
     */
    private function registerAttendance(array $referencesByUser): FakeCalendarAttendanceSource
    {
        $source = new FakeCalendarAttendanceSource($referencesByUser);
        app()->instance(FakeCalendarAttendanceSource::class, $source);
        app(CalendarAttendanceSourceRegistryInterface::class)->register(FakeCalendarAttendanceSource::class, 'example-module');

        return $source;
    }

    private function requireEmailVerification(): void
    {
        app(SettingsServiceInterface::class)->set('email_verification_enforced_since', '2026-01-01T00:00:00+00:00');
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
    }
}
