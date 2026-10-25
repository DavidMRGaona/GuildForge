import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { useFavicons } from '../useFavicons';

const appStore = reactive({ isDarkMode: true });

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): { props: Record<string, unknown> } => ({ props: {} }),
}));

vi.mock('@/stores/useAppStore', () => ({
    useAppStore: (): typeof appStore => appStore,
}));

function renderHead(theme: 'light' | 'dark'): void {
    const base = `/favicons/${theme}`;
    document.head.innerHTML = `
        <link rel="icon" sizes="32x32" href="${base}/favicon-32x32.png">
        <link rel="icon" sizes="16x16" href="${base}/favicon-16x16.png">
        <link rel="apple-touch-icon" href="${base}/apple-touch-icon.png">
        <link rel="shortcut icon" href="${base}/favicon.ico">
        <link rel="manifest" href="${base}/site.webmanifest">
    `;
}

function shortcutIconHref(): string | null {
    return document.querySelector('link[rel="shortcut icon"]')?.getAttribute('href') ?? null;
}

describe('useFavicons', () => {
    beforeEach(() => {
        appStore.isDarkMode = true;
    });

    it('switches the icons to the current theme', () => {
        renderHead('light');

        useFavicons();

        expect(shortcutIconHref()).toBe('/favicons/dark/favicon.ico');
    });

    it('leaves the links alone when they already match the theme', () => {
        renderHead('dark');
        const setHref = vi.spyOn(HTMLLinkElement.prototype, 'href', 'set');

        useFavicons();

        expect(setHref).not.toHaveBeenCalled();
    });
});
