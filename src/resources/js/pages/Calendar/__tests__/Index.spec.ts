import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import Index from '../Index.vue';
import CalendarSubscribeMenu from '@/components/calendar/CalendarSubscribeMenu.vue';
import EventCalendar from '@/components/calendar/EventCalendar.vue';
import EventsViewToggle from '@/components/events/EventsViewToggle.vue';
import en from '@/locales/en';
import es from '@/locales/es';

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): { url: string; props: Record<string, unknown> } => ({ url: '/', props: {} }),
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

function mountPage(props: Record<string, unknown> = {}): ReturnType<typeof mount> {
    return mount(Index, {
        props,
        global: {
            plugins: [createI18n({ legacy: false, locale: 'es', messages: { es, en } })],
            stubs: { EventCalendar: true, EventDetailPanel: true, HelpLink: true, Teleport: true },
        },
    });
}

describe('Calendar/Index', () => {
    it('links the calendar guide next to the page title', () => {
        const link = mountPage().get('h1 + help-link-stub');

        expect(link.attributes('guide')).toBe('core.events.find-events');
    });

    it('opens with the same title band as the other lists', () => {
        const band = mountPage().get('[data-testid="page-band"]');

        expect(band.classes()).toContain('page-band');
        expect(band.get('h1').text()).toBe('Calendario de eventos');
        expect(band.text()).toContain('Todas las actividades de la asociación de un vistazo');
    });

    it('offers the event list from its band', () => {
        const band = mountPage().get('[data-testid="page-band"]');

        expect(band.findComponent(EventsViewToggle).props('current')).toBe('calendar');
    });

    it('puts the personal filter in the title band', () => {
        const band = mountPage({ personalCalendar: true }).get('[data-testid="page-band"]');

        expect(band.find('#calendar-only-mine').exists()).toBe(true);
    });

    it('never offers the personal filter unless the page allows it', () => {
        const wrapper = mountPage();

        expect(wrapper.find('#calendar-only-mine').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Solo en lo que estoy inscrito');
    });

    it('filters both calendars to the signed-in person when the toggle is on', async () => {
        const wrapper = mountPage({ personalCalendar: true });
        const calendars = (): unknown[] =>
            wrapper.findAllComponents(EventCalendar).map((calendar) => calendar.props('onlyMine'));

        expect(wrapper.text()).toContain('Solo en lo que estoy inscrito');
        expect(calendars()).toEqual([false, false]);

        await wrapper.get('#calendar-only-mine').setValue(true);

        expect(calendars()).toEqual([true, true]);
    });

    it('offers the public subscription when the page sends its addresses', () => {
        const feed = {
            feedUrl: 'https://example.test/calendario.ics',
            webcalUrl: 'webcal://example.test/calendario.ics',
            googleUrl: 'https://calendar.google.com/calendar/r?cid=webcal%3A%2F%2Fexample.test',
            outlookUrl:
                'https://outlook.live.com/calendar/0/addfromweb?url=https%3A%2F%2Fexample.test',
        };

        const menu = mountPage({ publicCalendarFeed: feed }).findComponent(CalendarSubscribeMenu);

        expect(menu.exists()).toBe(true);
        expect(menu.props('links')).toEqual(feed);
    });

    it('hides the subscription without addresses', () => {
        expect(mountPage().findComponent(CalendarSubscribeMenu).exists()).toBe(false);
    });
});
