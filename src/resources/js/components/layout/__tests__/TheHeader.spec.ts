import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { reactive, ref } from 'vue';
import type { ShallowRef } from 'vue';
import TheHeader from '../TheHeader.vue';
import en from '@/locales/en';
import es from '@/locales/es';

const fits = ref(true);
/** Parts of the row the header asked the composable to observe besides the row itself */
let observedParts: ReadonlyArray<Readonly<ShallowRef<HTMLElement | null>>> = [];

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): { url: string; props: Record<string, unknown> } =>
        reactive({ url: '/', props: { appName: 'Example guild', navigation: { header: [] } } }),
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('@/composables/useFitsWithoutOverflow', () => ({
    useFitsWithoutOverflow: (
        _target: unknown,
        _recheckOn: unknown,
        parts: ReadonlyArray<Readonly<ShallowRef<HTMLElement | null>>> = []
    ): typeof fits => {
        observedParts = parts;
        return fits;
    },
}));

vi.mock('@/composables/useAuth', () => ({
    useAuth: (): { isAuthenticated: boolean } => ({ isAuthenticated: false }),
}));

vi.mock('@/composables/useRoutes', () => ({
    useRoutes: (): { home: string; search: string } => ({ home: '/', search: '/buscar' }),
}));

vi.mock('@/stores/useAppStore', () => ({
    useAppStore: (): { isDarkMode: boolean; isThemeToggleVisible: boolean } => ({
        isDarkMode: false,
        isThemeToggleVisible: false,
    }),
}));

function mountHeader(): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(TheHeader, {
        global: {
            plugins: [i18n],
            stubs: { TheNavigation: true, UserDropdown: true, AuthLinks: true },
        },
    });
}

function desktopBar(wrapper: VueWrapper): Element {
    const searchLink = wrapper.get('button[aria-label="Buscar"]').element;

    return searchLink.parentElement as Element;
}

function menuButton(wrapper: VueWrapper): ReturnType<VueWrapper['get']> {
    return wrapper.get('button[aria-controls="mobile-menu"]');
}

describe('TheHeader', () => {
    beforeEach(() => {
        fits.value = true;
        observedParts = [];
    });

    it('observes the logo, whose width changes when it loads or the theme switches', () => {
        const wrapper = mountHeader();
        const brand = wrapper.get('a[href="/"]').element;

        expect(observedParts.map((part) => part.value)).toEqual([brand.parentElement]);
    });

    it('shows the full bar from md up while it fits', () => {
        const wrapper = mountHeader();

        expect(desktopBar(wrapper).classList).toContain('md:flex');
        expect(menuButton(wrapper).element.parentElement?.classList).toContain('md:hidden');
    });

    it('falls back to the menu button at any width when the bar does not fit', () => {
        fits.value = false;

        const wrapper = mountHeader();

        expect(desktopBar(wrapper).classList).not.toContain('md:flex');
        expect(menuButton(wrapper).element.parentElement?.classList).not.toContain('md:hidden');
    });

    it('keeps the open menu visible at any width when the bar does not fit', async () => {
        fits.value = false;
        const wrapper = mountHeader();

        await menuButton(wrapper).trigger('click');

        const menu = wrapper.get('#mobile-menu');
        expect(menu.classes()).not.toContain('md:hidden');
        expect(menuButton(wrapper).attributes('aria-expanded')).toBe('true');
    });

    it('hides the open menu from md up while the bar fits', async () => {
        const wrapper = mountHeader();

        await menuButton(wrapper).trigger('click');

        expect(wrapper.get('#mobile-menu').classes()).toContain('md:hidden');
    });

    it('shows the site name when the site has no logo', () => {
        const brand = mountHeader().get('a[href="/"]');

        expect(brand.find('img').exists()).toBe(false);
        expect(brand.text()).toBe('Example guild');
    });

    it('sits above the page band below it, so its shadow is not painted over', () => {
        const header = mountHeader().get('header');

        expect(header.classes()).toEqual(
            expect.arrayContaining(['relative', 'z-30', 'shadow-sm', 'dark:border-b'])
        );
    });

    it('rings its buttons only on keyboard focus, not after a click', () => {
        const search = mountHeader().get('button[aria-label="Buscar"]');

        expect(search.classes()).toContain('focus-visible:ring-2');
        expect(search.classes()).not.toContain('focus:ring-2');
    });
});
