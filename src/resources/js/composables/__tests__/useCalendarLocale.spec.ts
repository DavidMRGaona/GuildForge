import { afterEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick, type VNode } from 'vue';
import { createI18n } from 'vue-i18n';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/vue3/daygrid';
import timeGridPlugin from '@fullcalendar/vue3/timegrid';
import { useCalendarLocale } from '../useCalendarLocale';
import es from '@/locales/es';
import en from '@/locales/en';

// A calendar with the toolbar both site calendars use, in the view under test
function mountCalendar(locale: 'es' | 'en', initialView: string): void {
    const Calendar = defineComponent({
        setup(): () => VNode {
            const calendarLocale = useCalendarLocale();
            return () =>
                h(FullCalendar, {
                    options: {
                        plugins: [dayGridPlugin, timeGridPlugin],
                        initialView,
                        initialDate: '2026-11-07',
                        locale: calendarLocale.value,
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay',
                        },
                    },
                });
        },
    });

    mount(Calendar, {
        attachTo: document.body,
        global: {
            plugins: [
                createI18n({ legacy: false, locale, fallbackLocale: 'es', messages: { es, en } }),
            ],
        },
    });
}

// FullCalendar puts each button's hint in its title (aria-label when it has an icon)
function hints(): string[] {
    return Array.from(document.body.querySelectorAll('button'))
        .map((button) => button.getAttribute('title') ?? button.getAttribute('aria-label') ?? '')
        .filter((hint) => hint !== '');
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('useCalendarLocale', () => {
    it('names the navigation buttons after the unit of the month view', async () => {
        mountCalendar('es', 'dayGridMonth');
        await nextTick();

        expect(hints()).toEqual(
            expect.arrayContaining(['Mes anterior', 'Mes siguiente', 'Este mes'])
        );
        expect(hints()).not.toContain('Mes antes');
    });

    it('names the navigation buttons after the unit of the week view', async () => {
        mountCalendar('es', 'timeGridWeek');
        await nextTick();

        expect(hints()).toEqual(
            expect.arrayContaining(['Semana anterior', 'Semana siguiente', 'Esta semana'])
        );
    });

    it('names the navigation buttons after the unit of the day view', async () => {
        mountCalendar('es', 'timeGridDay');
        await nextTick();

        expect(hints()).toEqual(expect.arrayContaining(['Día anterior', 'Día siguiente', 'Hoy']));
    });

    it('names each view button with correct Spanish', async () => {
        mountCalendar('es', 'dayGridMonth');
        await nextTick();

        expect(hints()).toEqual(
            expect.arrayContaining(['Vista de mes', 'Vista de semana', 'Vista de día'])
        );
        expect(hints()).not.toContain('Vista del semana');
    });

    it('translates the texts the Spanish FullCalendar locale lacks', async () => {
        mountCalendar('es', 'timeGridWeek');
        await nextTick();

        expect(document.body.textContent).not.toContain('Timed');
        expect(document.body.textContent).toContain('Por horas');
        expect(document.body.querySelector('[aria-label="Cambiar vista"]')).not.toBeNull();
    });

    it('keeps the English texts in English', async () => {
        mountCalendar('en', 'timeGridWeek');
        await nextTick();

        expect(hints()).toEqual(
            expect.arrayContaining(['Previous week', 'Next week', 'This week', 'Week view'])
        );
        expect(document.body.querySelector('[aria-label="Change view"]')).not.toBeNull();
    });
});
