import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import Index from '../Index.vue';
import type { SearchSource } from '@/types/search';
import en from '@/locales/en';
import es from '@/locales/es';

vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn() },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

const helpSource: SearchSource = {
    key: 'help',
    label: 'Ayuda',
    results: [
        {
            title: 'Apuntarse a una partida',
            url: '/ayuda/core.signup',
            context: 'Primeros pasos',
            snippet: [
                { text: 'Cómo ', match: false },
                { text: 'apuntarse', match: true },
                { text: ' <b>hoy</b>', match: false },
            ],
        },
    ],
};

function mountPage(sources: SearchSource[]): ReturnType<typeof mount> {
    return mount(Index, {
        props: { query: 'apuntarse', events: [], articles: [], sources, error: null },
        global: {
            plugins: [createI18n({ legacy: false, locale: 'es', messages: { es, en } })],
            stubs: { SearchInput: true },
        },
    });
}

describe('Search/Index extra sources', () => {
    it('renders a block per source with a link, context and a highlighted snippet', () => {
        const wrapper = mountPage([helpSource]);

        expect(wrapper.get('h2#search-source-help').text()).toBe('Ayuda');
        expect(wrapper.get('a[href="/ayuda/core.signup"]').text()).toContain(
            'Apuntarse a una partida'
        );
        expect(wrapper.text()).toContain('Primeros pasos');
        expect(wrapper.get('mark').text()).toBe('apuntarse');
    });

    it('never renders snippet text as HTML', () => {
        const wrapper = mountPage([helpSource]);

        expect(wrapper.find('b').exists()).toBe(false);
        expect(wrapper.text()).toContain('<b>hoy</b>');
    });

    it('does not show the no results message when only a source has results', () => {
        const wrapper = mountPage([helpSource]);

        expect(wrapper.text()).not.toContain('No se encontraron resultados');
    });

    it('shows the no results message when nothing matches anywhere', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain('No se encontraron resultados');
    });
});
