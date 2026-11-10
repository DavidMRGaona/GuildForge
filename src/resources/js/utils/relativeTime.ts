const UNITS: ReadonlyArray<[Intl.RelativeTimeFormatUnit, number]> = [
    ['year', 365 * 24 * 60 * 60],
    ['month', 30 * 24 * 60 * 60],
    ['week', 7 * 24 * 60 * 60],
    ['day', 24 * 60 * 60],
    ['hour', 60 * 60],
    ['minute', 60],
];

/**
 * «hace 5 minutos», «ayer», «hace 3 semanas»: how long ago an instant was, in the page's
 * locale. Under a minute it is «ahora». An unreadable instant gives an empty string.
 */
export function formatRelativeTime(
    iso: string | null,
    locale: string,
    now: Date = new Date()
): string {
    if (iso === null) {
        return '';
    }

    const instant = new Date(iso);
    if (Number.isNaN(instant.getTime())) {
        return '';
    }

    const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
    // A clock a little ahead of the server never says «dentro de»
    const seconds = Math.max(0, Math.round((now.getTime() - instant.getTime()) / 1000));

    for (const [unit, size] of UNITS) {
        if (seconds >= size) {
            return formatter.format(-Math.floor(seconds / size), unit);
        }
    }

    return formatter.format(0, 'second');
}
