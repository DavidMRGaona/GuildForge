import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import Index from '../Index.vue';
import en from '@/locales/en';
import es from '@/locales/es';

function mountPage(): ReturnType<typeof mount> {
    return mount(Index, {
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
});
