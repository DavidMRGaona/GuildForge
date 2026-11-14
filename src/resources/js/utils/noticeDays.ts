import { addVenueDays, venueDayKey } from '@/utils/datetime';

/** The headings of the «Avisos» page (core#671). */
export type NoticeDay = 'today' | 'yesterday' | 'thisWeek' | 'earlier';

export interface NoticeDayGroup<T> {
    day: NoticeDay;
    /** The notices of that day, with their position in the page (for the focus after hiding). */
    entries: { item: T; index: number }[];
}

/** Monday of the week of a 'YYYY-MM-DD' day: weeks start on Monday, as in Spain. */
function mondayOf(dayKey: string): string {
    // Noon UTC is the same calendar day whatever the venue zone
    const weekday = new Date(`${dayKey}T12:00:00Z`).getUTCDay();

    return addVenueDays(dayKey, -((weekday + 6) % 7));
}

/**
 * Which heading a notice goes under, by its calendar day in the venue's display zone (never the
 * browser's): today, yesterday, earlier this week (from Monday) or before. A missing or
 * unreadable instant goes with the earlier ones.
 */
export function noticeDayOf(iso: string | null, now: Date): NoticeDay {
    if (iso === null) {
        return 'earlier';
    }

    const instant = new Date(iso);
    if (Number.isNaN(instant.getTime())) {
        return 'earlier';
    }

    const day = venueDayKey(instant);
    const today = venueDayKey(now);

    if (day >= today) {
        return 'today';
    }
    if (day === addVenueDays(today, -1)) {
        return 'yesterday';
    }

    return day >= mondayOf(today) ? 'thisWeek' : 'earlier';
}

/**
 * The page's notices under their headings, in their order (newest first). Grouping is per page:
 * a page that starts in the middle of a day repeats that heading on top.
 */
export function groupByNoticeDay<T extends { notifiedAt: string | null }>(
    items: readonly T[],
    now: Date
): NoticeDayGroup<T>[] {
    const groups: NoticeDayGroup<T>[] = [];

    items.forEach((item, index) => {
        const day = noticeDayOf(item.notifiedAt, now);
        const last = groups[groups.length - 1];

        if (last?.day === day) {
            last.entries.push({ item, index });
        } else {
            groups.push({ day, entries: [{ item, index }] });
        }
    });

    return groups;
}
