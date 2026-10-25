import { computed, type ComputedRef } from 'vue';
import { useI18n } from 'vue-i18n';
import esLocale from '@fullcalendar/vue3/locales/es';
import type { LocaleInput } from '@fullcalendar/vue3';

const FULLCALENDAR_LOCALES: Record<string, LocaleInput> = {
    es: esLocale,
};

const UNITS = ['day', 'week', 'month', 'year'] as const;
type HintUnit = (typeof UNITS)[number] | 'other';

function hintUnit(unit: string | undefined): HintUnit {
    return UNITS.find((known) => known === unit) ?? 'other';
}

// The view hint gets the view name ("timeGridWeek"), not its unit
function viewUnit(viewName: string): HintUnit {
    return hintUnit(UNITS.find((unit) => viewName.toLowerCase().endsWith(unit)));
}

/**
 * FullCalendar's locale for the site's language, with the hints and labels taken from the site's
 * translations: FullCalendar 7's Spanish locale gets some wrong («Mes antes», «Vista del semana»,
 * because its view hint expects a unit and receives the view name) and lacks others, which then
 * show in English («Timed», «Change view»).
 */
export function useCalendarLocale(): ComputedRef<LocaleInput> {
    const { t, locale } = useI18n();

    return computed<LocaleInput>(() => ({
        ...(FULLCALENDAR_LOCALES[locale.value] ?? { code: locale.value }),
        prevHint: (_unitText: string, unit?: string) =>
            t(`calendar.fullcalendar.previous.${hintUnit(unit)}`),
        nextHint: (_unitText: string, unit?: string) =>
            t(`calendar.fullcalendar.next.${hintUnit(unit)}`),
        todayHint: (_unitText: string, unit?: string) =>
            t(`calendar.fullcalendar.current.${hintUnit(unit)}`),
        viewHint: (buttonText: string, viewName: string) =>
            t(`calendar.fullcalendar.view.${viewUnit(viewName)}`, { view: buttonText }),
        viewChangeHint: t('calendar.fullcalendar.viewChange'),
        timedText: t('calendar.fullcalendar.timed'),
        moreLinkHint: (count: number) => t('calendar.fullcalendar.moreLink', count),
    }));
}
