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

/** The tab at `index`, failing loudly when it is missing. */
function tabAt(wrapper: ReturnType<typeof mount>, index: number): DOMWrapper<Element> {
    const tab = wrapper.findAll('[role="tab"]')[index];
    if (tab === undefined) throw new Error(`No tab at ${index}`);

    return tab;
}

function mountTabs(active: string | null): ReturnType<typeof mount> {
    return mount(SearchTabs, {
        props: { tabs, active, query: 'rol & dados', controls: 'search-results' },
        attachTo: document.body,
        global: {
            plugins: [createI18n({ legacy: false, locale: 'es', messages: { es, en } })],
        },
    });
}

describe('SearchTabs', () => {
    it('renders a tab per type with counts', () => {
        const wrapper = mountTabs(null);
        const items = wrapper.findAll('[role="tab"]');

        expect(wrapper.find('[role="tablist"]').exists()).toBe(true);
        expect(items).toHaveLength(3);
        expect(tabAt(wrapper, 0).text()).toContain('Todo');
        expect(tabAt(wrapper, 0).text()).toContain('13');
        expect(tabAt(wrapper, 1).text()).toContain('Eventos');
        expect(tabAt(wrapper, 1).text()).toContain('12');
        // The count is announced with words, not just a bare number
        expect(tabAt(wrapper, 1).text()).toContain('12 resultados');
        expect(tabAt(wrapper, 2).text()).toContain('1 resultado');
        // «Todo» drops the type; the others ask for it; no tab ever carries a page
        expect(tabAt(wrapper, 0).attributes('href')).toBe('/buscar?q=rol+%26+dados');
        expect(tabAt(wrapper, 1).attributes('href')).toBe('/buscar?q=rol+%26+dados&tipo=events');
        expect(items.every((item) => !item.attributes('href')?.includes('pagina'))).toBe(true);
        expect(tabAt(wrapper, 1).attributes('aria-controls')).toBe('search-results');

        wrapper.unmount();
    });

    it('marks the active tab', () => {
        const wrapper = mountTabs('events');

        expect(tabAt(wrapper, 1).attributes('aria-selected')).toBe('true');
        expect(tabAt(wrapper, 1).attributes('aria-current')).toBe('page');
        expect(tabAt(wrapper, 1).attributes('tabindex')).toBe('0');
        expect(tabAt(wrapper, 0).attributes('aria-selected')).toBe('false');
        expect(tabAt(wrapper, 0).attributes('aria-current')).toBeUndefined();
        expect(tabAt(wrapper, 0).attributes('tabindex')).toBe('-1');
        expect(tabAt(wrapper, 1).attributes('id')).toBe('search-tab-events');
        expect(tabAt(wrapper, 0).attributes('id')).toBe('search-tab-all');

        wrapper.unmount();
    });

    it('moves the focus with the arrow keys, wrapping around', async () => {
        const wrapper = mountTabs(null);

        (tabAt(wrapper, 0).element as HTMLElement).focus();
        await tabAt(wrapper, 0).trigger('keydown', { key: 'ArrowRight' });
        expect(document.activeElement).toBe(tabAt(wrapper, 1).element);

        await tabAt(wrapper, 1).trigger('keydown', { key: 'ArrowLeft' });
        await tabAt(wrapper, 0).trigger('keydown', { key: 'ArrowLeft' });
        expect(document.activeElement).toBe(tabAt(wrapper, 2).element);

        await tabAt(wrapper, 2).trigger('keydown', { key: 'Home' });
        expect(document.activeElement).toBe(tabAt(wrapper, 0).element);

        await tabAt(wrapper, 0).trigger('keydown', { key: 'End' });
        expect(document.activeElement).toBe(tabAt(wrapper, 2).element);

        wrapper.unmount();
    });
});
