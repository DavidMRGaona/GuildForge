import { describe, expect, it, vi } from 'vitest';
import { mount, type DOMWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import SearchTabs from '../SearchTabs.vue';
import type { SearchTab } from '@/types/search';
import en from '@/locales/en';
import es from '@/locales/es';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

const tabs: SearchTab[] = [
    { key: null, label: 'Todo', count: 13 },
    { key: 'events', label: 'Eventos', count: 12 },
    { key: 'help', label: 'Ayuda', count: 1 },
];

function mountTabs(active: string | null): ReturnType<typeof mount> {
    return mount(SearchTabs, {
        props: { tabs, active, query: 'rol & dados' },
        global: {
            plugins: [createI18n({ legacy: false, locale: 'es', messages: { es, en } })],
        },
    });
}

/** The link at `index`, failing loudly when it is missing. */
function linkAt(wrapper: ReturnType<typeof mount>, index: number): DOMWrapper<Element> {
    const link = wrapper.findAll('nav a')[index];
    if (link === undefined) throw new Error(`No link at ${index}`);

    return link;
}

describe('SearchTabs', () => {
    it('renders a tab per type with counts', () => {
        const wrapper = mountTabs(null);

        expect(wrapper.get('nav').attributes('aria-label')).toBe('Tipos de resultado');
        expect(wrapper.findAll('nav li a')).toHaveLength(3);
        expect(linkAt(wrapper, 0).text()).toContain('Todo');
        expect(linkAt(wrapper, 0).text()).toContain('13');
        expect(linkAt(wrapper, 1).text()).toContain('Eventos');
        expect(linkAt(wrapper, 1).text()).toContain('12');
        // The count is announced with words, not just a bare number
        expect(linkAt(wrapper, 1).text()).toContain('12 resultados');
        expect(linkAt(wrapper, 2).text()).toContain('1 resultado');
        // «Todo» drops the type; the others ask for it; no tab ever carries a page
        expect(linkAt(wrapper, 0).attributes('href')).toBe('/buscar?q=rol+%26+dados');
        expect(linkAt(wrapper, 1).attributes('href')).toBe('/buscar?q=rol+%26+dados&tipo=events');
        expect(
            wrapper.findAll('nav a').every((link) => !link.attributes('href')?.includes('pagina'))
        ).toBe(true);
    });

    it('marks the active tab', () => {
        const wrapper = mountTabs('events');

        expect(linkAt(wrapper, 1).attributes('aria-current')).toBe('page');
        expect(linkAt(wrapper, 0).attributes('aria-current')).toBeUndefined();
        expect(linkAt(wrapper, 2).attributes('aria-current')).toBeUndefined();
    });

    it('is plain navigation, not an ARIA tab widget', () => {
        const wrapper = mountTabs('events');

        for (const selector of [
            '[role="tablist"]',
            '[role="tab"]',
            '[aria-selected]',
            '[aria-controls]',
            '[tabindex]',
        ]) {
            expect(wrapper.find(selector).exists()).toBe(false);
        }
    });

    it('marks no tab when the active key matches none', () => {
        const wrapper = mountTabs('galleries');

        expect(wrapper.findAll('nav a')).toHaveLength(3);
        expect(wrapper.find('[aria-current]').exists()).toBe(false);
        // Every link stays in the normal tab order
        expect(wrapper.find('[tabindex="-1"]').exists()).toBe(false);
    });
});
