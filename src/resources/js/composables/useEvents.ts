import { useI18n } from 'vue-i18n';
import type { Event } from '@/types/models';
import { stripHtml } from '@/utils/html';
import { VENUE_TIMEZONE, venueDayKey } from '@/utils/datetime';

interface UseEventsReturn {
    formatEventDate: (dateString: string) => string;
    formatDateRange: (startDate: string, endDate: string) => string;
    formatPrice: (price: number | null) => string;
    isUpcoming: (event: Event) => boolean;
    getExcerpt: (description: string, maxLength?: number) => string;
    upcomingEvents: (events: Event[]) => Event[];
    pastEvents: (events: Event[]) => Event[];
}

export function useEvents(): UseEventsReturn {
    const { locale, t } = useI18n();
    function formatEventDate(dateString: string): string {
        const date = new Date(dateString);
        return date.toLocaleDateString(locale.value, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            timeZone: VENUE_TIMEZONE,
        });
    }

    function formatDateRange(startDate: string, endDate: string): string {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const startDay = venueDayKey(start);
        const endDay = venueDayKey(end);

        if (startDay === endDay) {
            // Single day: "15 de enero de 2026"
            return start.toLocaleDateString(locale.value, {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
                timeZone: VENUE_TIMEZONE,
            });
        }

        // Multi-day same month: "15-17 de enero de 2026"
        if (startDay.slice(0, 7) === endDay.slice(0, 7)) {
            const firstDayNumber = Number(startDay.slice(8));
            const lastDayNumber = Number(endDay.slice(8));
            return `${firstDayNumber}-${lastDayNumber} de ${start.toLocaleDateString(locale.value, { month: 'long', year: 'numeric', timeZone: VENUE_TIMEZONE })}`;
        }

        // Multi-day different months: "15 ene - 2 feb 2026"
        const formatShort = (d: Date): string =>
            d.toLocaleDateString(locale.value, {
                day: 'numeric',
                month: 'short',
                timeZone: VENUE_TIMEZONE,
            });
        return `${formatShort(start)} - ${formatShort(end)} ${endDay.slice(0, 4)}`;
    }

    function formatPrice(price: number | null): string {
        if (price === null) return t('events.free');
        return `${price.toFixed(2)} €`;
    }

    function isUpcoming(event: Event): boolean {
        const startDate = new Date(event.startDate);
        const now = new Date();
        return startDate > now;
    }

    function getExcerpt(description: string, maxLength = 150): string {
        const cleanText = stripHtml(description);
        if (cleanText.length <= maxLength) {
            return cleanText;
        }
        return cleanText.slice(0, maxLength).trim() + '...';
    }

    function upcomingEvents(events: Event[]): Event[] {
        return events
            .filter((event) => isUpcoming(event))
            .sort((a, b) => new Date(a.startDate).getTime() - new Date(b.startDate).getTime());
    }

    function pastEvents(events: Event[]): Event[] {
        return events
            .filter((event) => !isUpcoming(event))
            .sort((a, b) => new Date(b.startDate).getTime() - new Date(a.startDate).getTime());
    }

    return {
        formatEventDate,
        formatDateRange,
        formatPrice,
        isUpcoming,
        getExcerpt,
        upcomingEvents,
        pastEvents,
    };
}
