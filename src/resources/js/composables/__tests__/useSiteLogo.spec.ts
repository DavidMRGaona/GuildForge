import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, reactive } from 'vue';
import { useSiteLogo } from '../useSiteLogo';

const appStore = reactive({ isDarkMode: false });
const page = reactive<{ props: Record<string, unknown> }>({ props: {} });

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): typeof page => page,
}));

vi.mock('@/stores/useAppStore', () => ({
    useAppStore: (): typeof appStore => appStore,
}));

describe('useSiteLogo', () => {
    beforeEach(() => {
        appStore.isDarkMode = false;
        page.props = { siteLogoLight: '/logo-light.png', siteLogoDark: '/logo-dark.png' };
    });

    it('returns the light logo with the light theme', () => {
        const { logo } = useSiteLogo();

        expect(logo.value).toBe('/logo-light.png');
    });

    it('returns the dark logo with the dark theme', () => {
        appStore.isDarkMode = true;

        const { logo } = useSiteLogo();

        expect(logo.value).toBe('/logo-dark.png');
    });

    it('returns null when the site has no logo for the current theme', () => {
        page.props = { siteLogoLight: '', siteLogoDark: null };

        const { logo } = useSiteLogo();

        expect(logo.value).toBeNull();
    });

    it('returns null once the image fails to load', () => {
        const { logo, markFailed } = useSiteLogo();

        markFailed();

        expect(logo.value).toBeNull();
    });

    it('tries again when the theme switches to another logo', async () => {
        const { logo, markFailed } = useSiteLogo();
        markFailed();

        appStore.isDarkMode = true;
        await nextTick();

        expect(logo.value).toBe('/logo-dark.png');
    });
});
