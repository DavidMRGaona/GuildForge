import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { reactive, ref } from 'vue';
import TheHeader from '../TheHeader.vue';
import en from '@/locales/en';
import es from '@/locales/es';

const fits = ref(true);

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): { url: string; props: Record<string, unknown> } =>
        reactive({ url: '/', props: { appName: 'Example guild', navigation: { header: [] } } }),
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('@/composables/useFitsWithoutOverflow', () => ({
    useFitsWithoutOverflow: (): typeof fits => fits,
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
    const searchLink = wrapper.get('a[aria-label="Buscar"]').element;

    return searchLink.parentElement as Element;
}

function menuButton(wrapper: VueWrapper): ReturnType<VueWrapper['get']> {
    return wrapper.get('button[aria-controls="mobile-menu"]');
}

describe('TheHeader', () => {
    beforeEach(() => {
        fits.value = true;
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
});
