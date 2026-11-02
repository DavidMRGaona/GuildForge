import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import Index from '../Index.vue';
import type { SearchHit, SearchTab } from '@/types/search';
import en from '@/locales/en';
import es from '@/locales/es';

interface ReloadOptions {
    data?: Record<string, unknown>;
    only?: string[];
    preserveUrl?: boolean;
    onSuccess?: () => void;
    onFinish?: () => void;
}

const routerReload = vi.fn<(options: ReloadOptions) => void>();

vi.mock('@inertiajs/vue3', () => ({
    router: {
        get: vi.fn(),
        reload: (options: ReloadOptions): void => routerReload(options),
    },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

function hit(index: number, overrides: Partial<SearchHit> = {}): SearchHit {
    return {
        type: 'events',
        typeLabel: 'Evento',
        title: `Partida ${index}`,
        url: `/eventos/partida-${index}`,
        meta: null,
        image: null,
        date: null,
        pastLabel: null,
        snippet: [],
        ...overrides,
    };
}

const tabs: SearchTab[] = [
    { key: null, label: 'Todo', count: 3 },
    { key: 'events', label: 'Eventos', count: 2 },
    { key: 'help', label: 'Ayuda', count: 1 },
];

interface PageProps {
    query: string;
    type: string | null;
    tabs: SearchTab[];
    results: SearchHit[];
    total: number;
    page: number;
    hasMore: boolean;
    error: 'minChars' | null;
}

function mountPage(overrides: Partial<PageProps> = {}): ReturnType<typeof mount> {
    const props: PageProps = {
        query: 'partida',
        type: null,
        tabs,
        results: [hit(1), hit(2)],
        total: 3,
        page: 1,
        hasMore: true,
        error: null,
        ...overrides,
    };

    return mount(Index, {
        props,
        attachTo: document.body,
        global: {
            plugins: [createI18n({ legacy: false, locale: 'es', messages: { es, en } })],
            stubs: { SearchInput: true, HelpLink: true },
        },
    });
}

describe('Search/Index', () => {
    it('links the search guide next to the page title', () => {
        const wrapper = mountPage();

        expect(wrapper.get('h1 + help-link-stub').attributes('guide')).toBe(
            'core.search.site-search'
        );

        wrapper.unmount();
    });

    it('renders the tabs and a row per result inside the tab panel', () => {
        const wrapper = mountPage({ type: 'events' });

        expect(wrapper.findAll('[role="tab"]')).toHaveLength(3);
        const panel = wrapper.get('[role="tabpanel"]');
        expect(panel.attributes('aria-labelledby')).toBe('search-tab-events');
        expect(panel.findAll('li')).toHaveLength(2);
        expect(panel.text()).toContain('Partida 1');
        expect(wrapper.get('[role="status"]').text()).toBe('3 resultados');

        wrapper.unmount();
    });

    it('appends the next page on show more', async () => {
        routerReload.mockClear();
        const wrapper = mountPage();

        await wrapper.get('[data-testid="search-show-more"]').trigger('click');

        expect(routerReload).toHaveBeenCalledTimes(1);
        const options = routerReload.mock.calls[0]?.[0] ?? {};
        expect(options.data).toEqual({ pagina: 2 });
        expect(options.only).toEqual(['results', 'page', 'hasMore']);
        // The address bar keeps «?q=»: reloading it must never land on page 2 alone
        expect(options.preserveUrl).toBe(true);
        expect(
            wrapper.get('[data-testid="search-show-more"]').attributes('disabled')
        ).toBeDefined();

        // Inertia merges the next page into `results`
        await wrapper.setProps({ results: [hit(1), hit(2), hit(3)], page: 2, hasMore: false });
        options.onSuccess?.();
        options.onFinish?.();
        await flushPromises();

        const rows = wrapper.findAll('[role="tabpanel"] li');
        expect(rows).toHaveLength(3);
        const firstNewRow = rows[2];
        expect(firstNewRow?.text()).toContain('Partida 3');
        // The focus goes to the first new row, so a keyboard user carries on from there
        expect(document.activeElement).toBe(firstNewRow?.get('a').element);
        expect(wrapper.find('[data-testid="search-show-more"]').exists()).toBe(false);

        wrapper.unmount();
    });

    it('shows the min chars warning', () => {
        const wrapper = mountPage({
            query: 'p',
            tabs: [],
            results: [],
            total: 0,
            hasMore: false,
            error: 'minChars',
        });

        expect(wrapper.text()).toContain('Escribe al menos 2 caracteres para buscar');
        expect(wrapper.text()).not.toContain('No se encontraron resultados');
        expect(wrapper.find('[role="tablist"]').exists()).toBe(false);

        wrapper.unmount();
    });

    it('shows no results with the query', () => {
        const wrapper = mountPage({ tabs: [], results: [], total: 0, hasMore: false });

        expect(wrapper.text()).toContain('No se encontraron resultados para "partida"');
        expect(wrapper.find('[role="tablist"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="search-show-more"]').exists()).toBe(false);

        wrapper.unmount();
    });

    it('shows nothing but the search box before a query', () => {
        const wrapper = mountPage({ query: '', tabs: [], results: [], total: 0, hasMore: false });

        expect(wrapper.text()).not.toContain('No se encontraron resultados');
        expect(wrapper.find('[role="tablist"]').exists()).toBe(false);

        wrapper.unmount();
    });
});
