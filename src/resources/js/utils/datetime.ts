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

const MINUTE_MS = 60_000;
// Any venue day starts between 14 hours before and 12 hours after UTC midnight of
// the same date (UTC+14 to UTC-12); one more hour each side leaves room to search.
const DAY_START_EARLIEST_MINUTES = -15 * 60;
const DAY_START_LATEST_MINUTES = 13 * 60;

const venueDayStartCache = new Map<string, Date>();

/**
 * First instant of a venue day ('YYYY-MM-DD').
 *
 * Usually venue midnight, but not on a day whose clocks jump over midnight
 * (America/Santiago, America/Havana on their DST day go from 23:59 straight to
 * 01:00): there it is 01:00. It is found by searching the minute where the venue
 * day begins, since time zone offsets and their changes fall on whole minutes.
 */
export function venueDayStart(dayKey: string): Date {
    const cached = venueDayStartCache.get(dayKey);
    if (cached !== undefined) {
        return new Date(cached.getTime());
    }

    const utcMidnight = Date.parse(`${dayKey}T00:00:00Z`);
    // Invariant: the venue day at `low` is before `dayKey`, at `high` it is not.
    let low = DAY_START_EARLIEST_MINUTES;
    let high = DAY_START_LATEST_MINUTES;

    while (high - low > 1) {
        const middle = Math.floor((low + high) / 2);

        if (venueDayKey(new Date(utcMidnight + middle * MINUTE_MS)) < dayKey) {
            low = middle;
        } else {
            high = middle;
        }
    }

    const start = new Date(utcMidnight + high * MINUTE_MS);
    venueDayStartCache.set(dayKey, start);

    return new Date(start.getTime());
}
