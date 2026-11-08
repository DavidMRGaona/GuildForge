import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, type PropType, type VNode } from 'vue';
import { createI18n } from 'vue-i18n';
import type { CalendarOptions, EventInput, EventSourceFuncInfo } from '@fullcalendar/vue3';
import EventCalendar from '../EventCalendar.vue';
import ModuleEventCalendar from '../ModuleEventCalendar.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { CalendarEntry } from '@/types/models';

vi.mock('@inertiajs/vue3', () => ({
    router: { visit: vi.fn() },
}));

// FullCalendar itself is not rendered: the stub keeps the options it receives
vi.mock('@fullcalendar/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@fullcalendar/vue3')>()),
    default: defineComponent({
        name: 'FullCalendar',
        props: { options: { type: Object as PropType<CalendarOptions>, required: true } },
        setup: () => (): VNode => h('div', { class: 'full-calendar-stub' }),
    }),
}));

let wrapper: VueWrapper | null = null;

function i18n(): ReturnType<typeof createI18n> {
    return createI18n({ legacy: false, locale: 'es', messages: { es, en } });
}

function mountComponent<P extends Record<string, unknown>>(
    component: typeof EventCalendar | typeof ModuleEventCalendar,
    props: P = {} as P
): VueWrapper {
    wrapper = mount(component, { props, global: { plugins: [i18n()] } });

    return wrapper;
}

function calendarOptions(mounted: VueWrapper): CalendarOptions {
    return mounted.findComponent({ name: 'FullCalendar' }).props('options') as CalendarOptions;
}

function entry(sourceType: string, sourceLabel: string, id: string): CalendarEntry {
    return {
        id,
        sourceType,
        sourceLabel,
        color: sourceType === 'event' ? 'primary' : 'accent',
        title: `${sourceLabel} ${id}`,
        description: '',
        start: '2026-11-20T18:00:00+01:00',
        end: null,
        location: null,
        imagePublicId: null,
        url: `/${sourceType}/${id}`,
        tags: [],
    };
}

/** Runs the calendar's event source as FullCalendar would, answering with the given entries. */
async function loadEntries(mounted: VueWrapper, entries: CalendarEntry[]): Promise<void> {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => new Response(JSON.stringify(entries), { status: 200 }))
    );
    const events = calendarOptions(mounted).events as (
        info: Pick<EventSourceFuncInfo, 'startStr' | 'endStr'>,
        success: (events: EventInput[]) => void,
        failure: (error: Error) => void
    ) => Promise<void>;

    await events(
        { startStr: '2026-11-01', endStr: '2026-12-01' },
        () => undefined,
        () => undefined
    );
    await flushPromises();
}

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    vi.unstubAllGlobals();
});

describe('EventCalendar', () => {
    it('keeps the month grid alone, with no view buttons, by default', () => {
        const options = calendarOptions(mountComponent(EventCalendar));

        expect(options.initialView).toBe('dayGridMonth');
        // Without views to choose, «Hoy» sits next to ‹ › on the title's row
        expect(options.headerToolbar).toEqual({
            start: 'title',
            center: '',
            end: 'today prev,next',
        });
        expect(options.headerToolbarClass).toBe('calendar-toolbar--responsive');
        expect(options.height).toBe('auto');
    });

    it('keeps the compact toolbar of the home page widget', () => {
        const options = calendarOptions(mountComponent(EventCalendar, { compact: true }));

        expect(options.headerToolbar).toEqual({ left: 'prev', center: 'title', right: 'next' });
        expect(options.headerToolbarClass).toBeUndefined();
    });

    it('starts on the given view and offers the given ones as buttons', () => {
        const options = calendarOptions(
            mountComponent(EventCalendar, {
                initialView: 'list',
                views: ['list', 'month'],
                height: 480,
            })
        );

        expect(options.initialView).toBe('listMonth');
        expect(options.headerToolbar).toEqual({
            start: 'title',
            center: 'today listMonth,dayGridMonth',
            end: 'prev,next',
        });
        expect(options.height).toBe(480);
        expect(options.buttons).toMatchObject({
            dayGridMonth: { text: 'Mes' },
            listMonth: { text: 'Lista' },
        });
        expect(options.noEventsText).toBe('No hay eventos en este mes');
    });
});

describe('ModuleEventCalendar', () => {
    it('shows the month grid without view buttons by default', () => {
        const options = calendarOptions(mountComponent(ModuleEventCalendar));

        expect(options.initialView).toBe('dayGridMonth');
        expect(options.headerToolbar).toMatchObject({ center: '', end: 'today prev,next' });
        expect(options.height).toBe('auto');
    });

    it('passes the initial view, the views and the height to the calendar', () => {
        const options = calendarOptions(
            mountComponent(ModuleEventCalendar, {
                initialView: 'list',
                views: ['month'],
                height: 600,
            })
        );

        expect(options.initialView).toBe('listMonth');
        expect(options.headerToolbar).toMatchObject({ center: 'today listMonth,dayGridMonth' });
        expect(options.height).toBe(600);
    });

    it('opens the entry page on click', () => {
        const calendar = mountComponent(ModuleEventCalendar).findComponent(EventCalendar);

        expect(calendar.props('navigateOnClick')).toBe(true);
    });

    it('lists the activity types of the loaded entries below the calendar', async () => {
        const mounted = mountComponent(ModuleEventCalendar);

        await loadEntries(mounted, [
            entry('event', 'Evento', '1'),
            entry('game-table', 'Partida', '2'),
            entry('event', 'Evento', '3'),
        ]);

        const legend = mounted.get('ul[aria-label="Tipos de actividad"]');
        expect(legend.findAll('li').map((item) => item.text())).toEqual(['Evento', 'Partida']);
    });

    it('leaves the legend out when asked to', async () => {
        const mounted = mountComponent(ModuleEventCalendar, { legend: false });

        await loadEntries(mounted, [entry('event', 'Evento', '1')]);

        expect(mounted.find('ul').exists()).toBe(false);
    });
});
