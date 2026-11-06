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
    onError?: () => void;
    onHttpException?: () => boolean | void;
    onNetworkError?: () => boolean | void;
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

    it('renders the tabs and a row per result', () => {
        const wrapper = mountPage({ type: 'events' });

        expect(wrapper.findAll('nav a')).toHaveLength(3);
        expect(wrapper.get('nav a[aria-current="page"]').text()).toContain('Eventos');
        const list = wrapper.get('[data-testid="search-results"]');
        expect(list.findAll('li')).toHaveLength(2);
        expect(list.text()).toContain('Partida 1');
        expect(wrapper.get('[data-testid="search-results-count"]').text()).toBe('3 resultados');
        expect(wrapper.find('[role="tabpanel"]').exists()).toBe(false);

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

        const rows = wrapper.findAll('[data-testid="search-results"] li');
        expect(rows).toHaveLength(3);
        const firstNewRow = rows[2];
        expect(firstNewRow?.text()).toContain('Partida 3');
        // The focus goes to the first new row, so a keyboard user carries on from there
        expect(document.activeElement).toBe(firstNewRow?.get('a').element);
        expect(wrapper.find('[data-testid="search-show-more"]').exists()).toBe(false);

        wrapper.unmount();
    });

    it('re-enables show more and says so when the next page fails', async () => {
        routerReload.mockClear();
        const wrapper = mountPage();
        const button = (): ReturnType<typeof wrapper.get> =>
            wrapper.get('[data-testid="search-show-more"]');
        const message = (): string => wrapper.get('[data-testid="search-show-more-error"]').text();

        expect(wrapper.get('[data-testid="search-show-more-error"]').attributes('role')).toBe(
            'status'
        );
        expect(message()).toBe('');

        // A server error: the handler keeps Inertia's error dialog away
        await button().trigger('click');
        const options = routerReload.mock.calls[0]?.[0] ?? {};
        expect(options.onHttpException?.()).toBe(false);
        options.onFinish?.();
        await flushPromises();

        expect(button().attributes('disabled')).toBeUndefined();
        expect(message()).toBe('No se han podido cargar más resultados. Inténtalo de nuevo.');
        expect(wrapper.findAll('[data-testid="search-results"] li')).toHaveLength(2);

        // Trying again clears the message; a network error brings it back
        await button().trigger('click');
        expect(message()).toBe('');
        const retry = routerReload.mock.calls[1]?.[0] ?? {};
        expect(retry.onNetworkError?.()).toBe(false);
        await flushPromises();
        expect(message()).toBe('No se han podido cargar más resultados. Inténtalo de nuevo.');

        // Validation errors take the same path
        await button().trigger('click');
        routerReload.mock.calls[2]?.[0]?.onError?.();
        await flushPromises();
        expect(button().attributes('disabled')).toBeUndefined();
        expect(message()).toBe('No se han podido cargar más resultados. Inténtalo de nuevo.');

        // A new query starts clean
        await wrapper.setProps({ query: 'dados' });
        expect(message()).toBe('');

        wrapper.unmount();
    });

    it('leaves no gap under show more while there is no error to say', () => {
        const wrapper = mountPage();

        // Still in the page for screen readers, but out of the layout
        expect(wrapper.get('[data-testid="search-show-more-error"]').classes()).toEqual([
            'sr-only',
        ]);

        wrapper.unmount();
    });

    it('a cancelled next page re-enables show more without an error', async () => {
        routerReload.mockClear();
        const wrapper = mountPage();

        await wrapper.get('[data-testid="search-show-more"]').trigger('click');
        const options = routerReload.mock.calls[0]?.[0] ?? {};
        // A tab or a new query cancels the request in flight: Inertia then only calls onFinish
        options.onFinish?.();
        await flushPromises();

        expect(
            wrapper.get('[data-testid="search-show-more"]').attributes('disabled')
        ).toBeUndefined();
        expect(wrapper.get('[data-testid="search-show-more-error"]').text()).toBe('');

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
        expect(wrapper.find('nav').exists()).toBe(false);

        wrapper.unmount();
    });

    it('shows no results with the query', () => {
        const wrapper = mountPage({ tabs: [], results: [], total: 0, hasMore: false });

        expect(wrapper.text()).toContain('No se encontraron resultados para "partida"');
        expect(wrapper.find('nav').exists()).toBe(false);
        expect(wrapper.find('[data-testid="search-show-more"]').exists()).toBe(false);

        wrapper.unmount();
    });

    it('shows nothing but the search box before a query', () => {
        const wrapper = mountPage({ query: '', tabs: [], results: [], total: 0, hasMore: false });

        expect(wrapper.text()).not.toContain('No se encontraron resultados');
        expect(wrapper.find('nav').exists()).toBe(false);

        wrapper.unmount();
    });
});
