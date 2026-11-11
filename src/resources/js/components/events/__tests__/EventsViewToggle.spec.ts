import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import EventsViewToggle from '../EventsViewToggle.vue';
import en from '@/locales/en';
import es from '@/locales/es';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

function mountToggle(current: 'list' | 'calendar'): ReturnType<typeof mount> {
    return mount(EventsViewToggle, {
        props: { current },
        global: { plugins: [createI18n({ legacy: false, locale: 'es', messages: { es, en } })] },
    });
}

describe('EventsViewToggle', () => {
    it('links the event list and the calendar inside a labelled navigation', () => {
        const wrapper = mountToggle('list');

        expect(wrapper.get('nav').attributes('aria-label')).toBe('Cambiar de vista');
        expect(wrapper.findAll('a').map((link) => link.attributes('href'))).toEqual([
            '/eventos',
            '/calendario',
        ]);
        expect(wrapper.findAll('a').map((link) => link.attributes('aria-label'))).toEqual([
            'Ver en lista',
            'Ver en calendario',
        ]);
    });

    it.each([
        ['list', 0],
        ['calendar', 1],
    ] as const)('marks the %s view as the current page', (current, index) => {
        const links = mountToggle(current).findAll('a');

        expect(links.map((link) => link.attributes('aria-current'))).toEqual(
            links.map((_, position) => (position === index ? 'page' : undefined))
        );
    });
});
