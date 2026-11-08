import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { reactive } from 'vue';
import AuthLayout from '../AuthLayout.vue';
import en from '@/locales/en';
import es from '@/locales/es';

const appStore = reactive({
    isDarkMode: false,
    setThemeSettings: (): void => undefined,
    initTheme: (): void => undefined,
});
const page = reactive<{ url: string; props: Record<string, unknown> }>({ url: '/', props: {} });

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): typeof page => page,
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('@/composables/useRoutes', () => ({
    useRoutes: (): { home: string } => ({ home: '/' }),
}));

vi.mock('@/stores/useAppStore', () => ({
    useAppStore: (): typeof appStore => appStore,
}));

function mountLayout(): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(AuthLayout, {
        props: { title: 'Iniciar sesión' },
        global: { plugins: [i18n], stubs: { Teleport: true } },
    });
}

function brandLink(wrapper: VueWrapper): ReturnType<VueWrapper['get']> {
    return wrapper.get('a[href="/"]');
}

describe('AuthLayout', () => {
    beforeEach(() => {
        appStore.isDarkMode = false;
        page.props = {
            appName: 'Example guild',
            siteLogoLight: '/logo-light.png',
            siteLogoDark: '/logo-dark.png',
        };
    });

    it('shows the site logo linking home, named after the site', () => {
        const img = brandLink(mountLayout()).get('img');

        expect(img.attributes('src')).toBe('/logo-light.png');
        expect(img.attributes('alt')).toBe('Example guild');
    });

    it('shows the dark logo with the dark theme', () => {
        appStore.isDarkMode = true;

        expect(brandLink(mountLayout()).get('img').attributes('src')).toBe('/logo-dark.png');
    });

    it('shows the site name instead when there is no logo', () => {
        page.props = { ...page.props, siteLogoLight: null, siteLogoDark: null };

        const link = brandLink(mountLayout());

        expect(link.find('img').exists()).toBe(false);
        expect(link.text()).toBe('Example guild');
    });

    it('shows the site name instead when the logo does not load', async () => {
        const wrapper = mountLayout();

        await brandLink(wrapper).get('img').trigger('error');

        expect(brandLink(wrapper).find('img').exists()).toBe(false);
        expect(brandLink(wrapper).text()).toBe('Example guild');
    });
});
