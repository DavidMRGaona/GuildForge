import { computed, unref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { MaybeRef } from 'vue';

export interface EventDateBadge {
    day: string;
    month: string;
}

const MONTH_ABBREVIATIONS_ES = 'ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|OCT|NOV|DIC';
const MONTH_ABBREVIATIONS_EN = 'JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC';

const MONTH_ABBREVIATIONS: Record<string, string> = {
    es: MONTH_ABBREVIATIONS_ES,
    en: MONTH_ABBREVIATIONS_EN,
};

export function useEventDateBadge(localeParam?: MaybeRef<string>): {
    getDateBadge: (dateString: string) => EventDateBadge;
} {
    // Use provided locale (ref or string) or fall back to i18n locale
    const { locale: i18nLocale } = localeParam === undefined ? useI18n() : { locale: null };
    const locale = computed(() => {
        if (localeParam !== undefined) {
            return unref(localeParam);
        }
        return i18nLocale?.value ?? 'es';
    });

    // Reactive month abbreviations based on current locale
    const months = computed(() => {
        const abbreviations = MONTH_ABBREVIATIONS[locale.value] ?? MONTH_ABBREVIATIONS_ES;
        return abbreviations.split('|');
    });

    function getDateBadge(dateString: string): EventDateBadge {
        const date = new Date(dateString);
        const day = date.getDate().toString();
        const monthIndex = date.getMonth();
        // months array is always 12 elements, monthIndex is always 0-11
        const month = months.value[monthIndex] as string;

        return { day, month };
    }

    return { getDateBadge };
}
