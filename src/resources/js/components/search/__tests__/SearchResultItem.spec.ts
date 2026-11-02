import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import SearchResultItem from '../SearchResultItem.vue';
import type { SearchHit } from '@/types/search';
import { setCloudinaryConfig } from '@/utils/cloudinary';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

const pastEvent: SearchHit = {
    type: 'events',
    typeLabel: 'Evento',
    title: 'Jornadas de rol',
    url: '/eventos/jornadas-de-rol',
    meta: 'Local de la asociación',
    image: 'events/2026/11/poster.jpg',
    date: 'sáb 15 nov · 10:00',
    pastLabel: 'Celebrado',
    snippet: [
        { text: 'Una tarde de ', match: false },
        { text: 'rol', match: true },
        { text: ' <b>abierta</b>', match: false },
    ],
};

describe('SearchResultItem', () => {
    beforeEach(() => {
        setCloudinaryConfig({ cloudName: 'demo', prefix: '' });
    });

    afterEach(() => {
        globalThis.__guildforgeCloudinary = undefined;
    });

    it('renders a row with type chip, date and past label', () => {
        const wrapper = mount(SearchResultItem, { props: { hit: pastEvent } });

        const link = wrapper.get('a');
        expect(link.attributes('href')).toBe('/eventos/jornadas-de-rol');
        expect(wrapper.get('[data-testid="search-hit-title"]').text()).toBe('Jornadas de rol');
        expect(wrapper.get('[data-testid="search-hit-type"]').text()).toBe('Evento');
        expect(wrapper.get('[data-testid="search-hit-details"]').text()).toBe(
            'sáb 15 nov · 10:00 · Local de la asociación'
        );
        expect(wrapper.get('[data-testid="search-hit-past"]').text()).toBe('Celebrado');
        expect(wrapper.get('mark').text()).toBe('rol');
        // Snippet text is never rendered as HTML
        expect(wrapper.find('b').exists()).toBe(false);

        const image = wrapper.get('img');
        expect(image.attributes('src')).toContain('res.cloudinary.com/demo/image/upload/');
        expect(image.attributes('src')).toContain('events/2026/11/poster');
        // Decorative: the title already names the row
        expect(image.attributes('alt')).toBe('');
    });

    it('leaves out what the hit does not have', () => {
        const wrapper = mount(SearchResultItem, {
            props: {
                hit: {
                    ...pastEvent,
                    type: 'help',
                    typeLabel: 'Guía',
                    meta: 'Primeros pasos',
                    image: null,
                    date: null,
                    pastLabel: null,
                    snippet: [],
                },
            },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('[data-testid="search-hit-past"]').exists()).toBe(false);
        expect(wrapper.get('[data-testid="search-hit-details"]').text()).toBe('Primeros pasos');
        expect(wrapper.find('mark').exists()).toBe(false);
    });
});
