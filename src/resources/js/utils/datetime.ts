const DEFAULT_VENUE_TIMEZONE = 'Europe/Madrid';

// The server announces the zone in the layout; read once at load because the
// formatters below are built at import time.
function resolveVenueTimezone(): string {
    if (typeof document === 'undefined') {
        return DEFAULT_VENUE_TIMEZONE;
    }

    const announced = document
        .querySelector('meta[name="display-timezone"]')
        ?.getAttribute('content')
        ?.trim();

    if (!announced) {
        return DEFAULT_VENUE_TIMEZONE;
    }

    // An unknown zone would make every formatter below throw at import time
    try {
        new Intl.DateTimeFormat('en', { timeZone: announced });
    } catch {
        return DEFAULT_VENUE_TIMEZONE;
    }

    return announced;
}

/**
 * Timezone of the guild's venue.
 *
 * Events are held on site, so dates are always displayed in the venue's
 * timezone regardless of where the visitor is browsing from. Without this,
 * `toLocale*` falls back to the browser timezone and shows an hour that does
 * not match the announced one.
 */
export const VENUE_TIMEZONE: string = resolveVenueTimezone();

const dayKeyFormatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: VENUE_TIMEZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

/**
 * Calendar day of an instant as seen at the venue, formatted as 'YYYY-MM-DD'.
 *
 * Use this instead of `getFullYear()`/`getMonth()`/`getDate()` when grouping or
 * comparing days: those getters resolve against the browser timezone, which can
 * place an event on a different day than the venue's.
 */
export function venueDayKey(date: Date): string {
    return dayKeyFormatter.format(date);
}

/**
 * Shift a venue day key ('YYYY-MM-DD') by a whole number of days.
 *
 * A day key is a calendar date, not an instant, so the sum needs no timezone:
 * it is done and read back in UTC, where every day lasts exactly 24 hours.
 * Reading the result in the venue zone instead would shift it by a day in zones
 * from UTC+12 onwards, where noon UTC is already the next day (core#684).
 */
export function addVenueDays(dayKey: string, days: number): string {
    return new Date(Date.parse(`${dayKey}T00:00:00Z`) + days * 86_400_000)
        .toISOString()
        .slice(0, 10);
}
