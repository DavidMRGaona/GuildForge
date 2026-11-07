import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import Index from '../Index.vue';
import EventCalendar from '@/components/calendar/EventCalendar.vue';
import en from '@/locales/en';
import es from '@/locales/es';

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
});
