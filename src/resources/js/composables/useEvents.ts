import { useI18n } from 'vue-i18n';
import type { Event } from '@/types/models';

interface UseEventsReturn {
    formatEventDate: (dateString: string) => string;
    formatDateRange: (startDate: string, endDate: string | null) => string;
    formatPrice: (price: number | null) => string;
    isUpcoming: (event: Event) => boolean;
    getExcerpt: (description: string, maxLength?: number) => string;
    upcomingEvents: (events: Event[]) => Event[];
    pastEvents: (events: Event[]) => Event[];
}

export function useEvents(): UseEventsReturn {
    const { locale } = useI18n();
    function formatEventDate(dateString: string): string {
        const date = new Date(dateString);
        return date.toLocaleDateString(locale.value, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }

    function formatDateRange(startDate: string, endDate: string | null): string {
        const start = new Date(startDate);
        const end = endDate ? new Date(endDate) : null;

        if (!end || start.toDateString() === end.toDateString()) {
            // Single day: "15 de enero de 2026"
            return start.toLocaleDateString(locale.value, {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        }

        // Multi-day same month: "15-17 de enero de 2026"
        if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
            return `${start.getDate()}-${end.getDate()} de ${start.toLocaleDateString(locale.value, { month: 'long', year: 'numeric' })}`;
        }

        // Multi-day different months: "15 ene - 2 feb 2026"
        const formatShort = (d: Date): string =>
            d.toLocaleDateString(locale.value, { day: 'numeric', month: 'short' });
        return `${formatShort(start)} - ${formatShort(end)} ${end.getFullYear()}`;
    }

    function formatPrice(price: number | null): string {
        if (price === null) return 'Gratuito';
        return `${price.toFixed(2)} €`;
    }

    function isUpcoming(event: Event): boolean {
        const startDate = new Date(event.startDate);
        const now = new Date();
        return startDate > now;
    }

    function getExcerpt(description: string, maxLength = 150): string {
        if (description.length <= maxLength) {
            return description;
        }
        return description.slice(0, maxLength).trim() + '...';
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
