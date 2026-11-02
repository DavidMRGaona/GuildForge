import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { DOMWrapper, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { nextTick, reactive, ref } from 'vue';
import SearchPanel from '../SearchPanel.vue';
import TheHeader from '@/components/layout/TheHeader.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { SearchHit } from '@/types/search';

const mocks = vi.hoisted(() => ({
    visit: vi.fn(),
    calls: [] as string[],
}));

const page = reactive({
    url: '/',
    props: { appName: 'Example guild', navigation: { header: [] } },
});

const suggestions = {
    query: ref(''),
    results: ref<SearchHit[]>([]),
    total: ref(0),
    loading: ref(false),
    failed: ref(false),
    reset: (): void => {
        suggestions.query.value = '';
        suggestions.results.value = [];
        suggestions.total.value = 0;
        suggestions.failed.value = false;
    },
};

vi.mock('@inertiajs/vue3', () => ({
    router: {
        visit: (...args: unknown[]): void => {
            mocks.calls.push('visit');
            mocks.visit(...args);
        },
    },
    usePage: (): typeof page => page,
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('@/composables/useSearchSuggestions', () => ({
    useSearchSuggestions: (): typeof suggestions => suggestions,
}));

vi.mock('@/composables/useFitsWithoutOverflow', () => ({
    useFitsWithoutOverflow: (): { value: boolean } => ({ value: true }),
}));

vi.mock('@/composables/useAuth', () => ({
    useAuth: (): { isAuthenticated: boolean } => ({ isAuthenticated: false }),
}));

vi.mock('@/stores/useAppStore', () => ({
    useAppStore: (): { isDarkMode: boolean; isThemeToggleVisible: boolean } => ({
        isDarkMode: false,
        isThemeToggleVisible: false,
    }),
}));

function makeHit(title: string): SearchHit {
    return {
        type: 'events',
        typeLabel: 'Evento',
        title,
        url: `/eventos/${title.toLowerCase()}`,
        meta: null,
        image: null,
        date: 'sáb 15 nov · 10:00',
        pastLabel: null,
        snippet: [],
    };
}

function i18n(): ReturnType<typeof createI18n> {
    return createI18n({ legacy: false, locale: 'es', messages: { es, en } });
}

const wrappers: VueWrapper[] = [];

function mountPanel(open = true): VueWrapper {
    const wrapper: VueWrapper = mount(SearchPanel, {
        attachTo: document.body,
        props: {
            open,
            'onUpdate:open': (value: boolean): void => {
                mocks.calls.push(value ? 'open' : 'close');
                void wrapper.setProps({ open: value });
            },
        },
        global: { plugins: [i18n()] },
    });
    wrappers.push(wrapper);

    return wrapper;
}

function mountHeader(): VueWrapper {
    const wrapper = mount(TheHeader, {
        attachTo: document.body,
        global: {
            plugins: [i18n()],
            stubs: { TheNavigation: true, UserDropdown: true, AuthLinks: true },
        },
    });
    wrappers.push(wrapper);

    return wrapper;
}

/** The panel is teleported to the body, outside the mounted wrapper. */
function dom(): DOMWrapper<Element> {
    return new DOMWrapper(document.body);
}

function combobox(): ReturnType<DOMWrapper<Element>['get']> {
    return dom().get('input[role="combobox"]');
}

function pressSlash(target: EventTarget, init: KeyboardEventInit = {}): KeyboardEvent {
    const event = new KeyboardEvent('keydown', {
        key: '/',
        bubbles: true,
        cancelable: true,
        ...init,
    });
    target.dispatchEvent(event);

    return event;
}

describe('SearchPanel', () => {
    beforeEach(() => {
        mocks.visit.mockReset();
        mocks.calls.length = 0;
        page.url = '/';
        suggestions.reset();
        suggestions.loading.value = false;
    });

    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
        document.body.innerHTML = '';
    });

    it('opens from the header button and focuses the input', async () => {
        mountHeader();
        expect(dom().find('[role="dialog"]').exists()).toBe(false);

        await dom().get('button[aria-label="Buscar"]').trigger('click');
        await nextTick();

        const dialog = dom().get('[role="dialog"]');
        expect(dialog.attributes('aria-modal')).toBe('true');
        expect(dialog.attributes('aria-label')).toBe('Buscar en la web');
        expect(document.activeElement).toBe(combobox().element);
        expect(combobox().attributes('placeholder')).toBe('Buscar eventos, mesas, artículos…');
    });

    it('moves the active option with arrows', async () => {
        suggestions.query.value = 'dra';
        suggestions.results.value = [makeHit('Uno'), makeHit('Dos'), makeHit('Tres')];
        suggestions.total.value = 3;
        mountPanel();
        const input = combobox();
        const options = (): string[] =>
            dom()
                .findAll('[role="option"]')
                .map((option) => option.attributes('id') ?? '');

        expect(input.attributes('aria-expanded')).toBe('true');
        expect(input.attributes('aria-controls')).toBe(
            dom().get('[role="listbox"]').attributes('id')
        );
        expect(input.attributes('aria-activedescendant')).toBeUndefined();

        await input.trigger('keydown', { key: 'ArrowDown' });
        expect(input.attributes('aria-activedescendant')).toBe(options()[0]);
        expect(dom().findAll('[role="option"]')[0]?.attributes('aria-selected')).toBe('true');

        await input.trigger('keydown', { key: 'ArrowDown' });
        await input.trigger('keydown', { key: 'ArrowDown' });
        expect(input.attributes('aria-activedescendant')).toBe(options()[2]);

        await input.trigger('keydown', { key: 'ArrowDown' });
        expect(input.attributes('aria-activedescendant')).toBe(options()[0]);

        await input.trigger('keydown', { key: 'ArrowUp' });
        expect(input.attributes('aria-activedescendant')).toBe(options()[2]);
        expect(dom().findAll('[role="option"]')[0]?.attributes('aria-selected')).toBe('false');
    });

    it('enter opens the active suggestion', async () => {
        suggestions.query.value = 'dra';
        suggestions.results.value = [makeHit('Uno'), makeHit('Dos')];
        suggestions.total.value = 2;
        mountPanel();
        const input = combobox();

        await input.trigger('keydown', { key: 'ArrowDown' });
        await input.trigger('keydown', { key: 'ArrowDown' });
        await input.trigger('keydown', { key: 'Enter' });

        expect(mocks.visit).toHaveBeenCalledWith('/eventos/dos');
        // The panel closes before the visit starts
        expect(mocks.calls).toEqual(['close', 'visit']);
        expect(dom().find('[role="dialog"]').exists()).toBe(false);
    });

    it('enter without active option goes to the results page', async () => {
        suggestions.query.value = ' dragón ';
        suggestions.results.value = [makeHit('Uno')];
        suggestions.total.value = 1;
        mountPanel();

        await combobox().trigger('keydown', { key: 'Enter' });

        expect(mocks.visit).toHaveBeenCalledWith('/buscar?q=drag%C3%B3n');
        expect(mocks.calls).toEqual(['close', 'visit']);
    });

    it('enter still goes to the results page when suggestions failed', async () => {
        suggestions.query.value = 'dragón';
        suggestions.failed.value = true;
        mountPanel();
        const input = combobox();

        expect(input.attributes('aria-expanded')).toBe('false');

        await input.trigger('keydown', { key: 'ArrowDown' });
        await input.trigger('keydown', { key: 'Enter' });

        expect(mocks.visit).toHaveBeenCalledWith('/buscar?q=drag%C3%B3n');
    });

    it('escape closes and returns focus', async () => {
        mountHeader();
        const trigger = dom().get('button[aria-label="Buscar"]');
        (trigger.element as HTMLElement).focus();
        await trigger.trigger('click');
        await nextTick();

        await combobox().trigger('keydown', { key: 'Escape' });
        await nextTick();

        expect(dom().find('[role="dialog"]').exists()).toBe(false);
        expect(document.activeElement).toBe(trigger.element);
        expect(document.body.style.overflow).toBe('');
    });

    it('slash opens the panel unless typing in a field', async () => {
        mountPanel(false);
        const field = document.createElement('textarea');
        const editable = document.createElement('div');
        editable.setAttribute('contenteditable', 'true');
        const select = document.createElement('select');
        const input = document.createElement('input');
        document.body.append(field, editable, select, input);

        for (const target of [field, editable, select, input]) {
            const event = pressSlash(target);
            expect(event.defaultPrevented).toBe(false);
        }
        expect(pressSlash(document.body, { ctrlKey: true }).defaultPrevented).toBe(false);
        expect(pressSlash(document.body, { metaKey: true }).defaultPrevented).toBe(false);
        expect(mocks.calls).toEqual([]);

        const event = pressSlash(document.body);
        await nextTick();
        await nextTick();

        expect(event.defaultPrevented).toBe(true);
        expect(mocks.calls).toEqual(['open']);
        expect(document.activeElement).toBe(combobox().element);
        expect(document.body.style.overflow).toBe('hidden');
    });

    it('shows see all with the total', async () => {
        suggestions.query.value = 'dragón';
        suggestions.results.value = [makeHit('Uno'), makeHit('Dos')];
        suggestions.total.value = 14;
        mountPanel();

        const seeAll = dom().get('[data-testid="search-panel-see-all"]');
        expect(seeAll.text()).toBe('Ver todos los resultados (14)');
        expect(seeAll.attributes('href')).toBe('/buscar?q=drag%C3%B3n');
        expect(dom().get('[aria-live="polite"]').text()).toBe('2 sugerencias');

        await seeAll.trigger('click');

        expect(mocks.visit).toHaveBeenCalledWith('/buscar?q=drag%C3%B3n');
        expect(mocks.calls).toEqual(['close', 'visit']);
    });

    it('keeps tab inside the panel', async () => {
        suggestions.query.value = 'dragón';
        suggestions.results.value = [makeHit('Uno')];
        suggestions.total.value = 1;
        mountPanel();
        await nextTick();
        const seeAll = dom().get('[data-testid="search-panel-see-all"]');

        (seeAll.element as HTMLElement).focus();
        await seeAll.trigger('keydown', { key: 'Tab' });
        expect(document.activeElement).toBe(combobox().element);

        await combobox().trigger('keydown', { key: 'Tab', shiftKey: true });
        expect(document.activeElement).toBe(seeAll.element);
    });

    it('closes when another page is visited', async () => {
        mountPanel();

        page.url = '/eventos';
        await nextTick();
        await nextTick();

        expect(mocks.calls).toEqual(['close']);
        expect(dom().find('[role="dialog"]').exists()).toBe(false);
    });
});
