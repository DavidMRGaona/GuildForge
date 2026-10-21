import { computed, type ComputedRef } from 'vue';
import { useI18n } from 'vue-i18n';
import esLocale from '@fullcalendar/vue3/locales/es';
import type { LocaleInput } from '@fullcalendar/vue3';

const FULLCALENDAR_LOCALES: Record<string, LocaleInput> = {
    es: esLocale,
};

export function useCalendarLocale(): ComputedRef<LocaleInput | string> {
    const { locale } = useI18n();
    return computed<LocaleInput | string>(() => FULLCALENDAR_LOCALES[locale.value] ?? locale.value);
}
