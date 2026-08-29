/**
 * Timezone of the guild's venue.
 *
 * Events are held on site, so dates are always displayed in the venue's
 * timezone regardless of where the visitor is browsing from. Without this,
 * `toLocale*` falls back to the browser timezone and shows an hour that does
 * not match the announced one.
 */
export const VENUE_TIMEZONE = 'Europe/Madrid';

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
 * The arithmetic is anchored at noon UTC, which always falls inside the venue
 * day whatever the offset, so it never lands on the hour a daylight-saving
 * transition adds or removes.
 */
export function addVenueDays(dayKey: string, days: number): string {
    return venueDayKey(new Date(Date.parse(`${dayKey}T12:00:00Z`) + days * 86_400_000));
}
