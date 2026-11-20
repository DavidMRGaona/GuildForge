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
        <link rel="shortcut icon" href="${base}/favicon.ico">
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

    it('leaves the home screen icon and the manifest to the installable site', () => {
        document.head.innerHTML = `
            <link rel="icon" sizes="32x32" href="/favicons/light/favicon-32x32.png">
            <link rel="apple-touch-icon" href="https://res.cloudinary.com/demo/apple-touch.jpg">
            <link rel="manifest" href="/manifest.webmanifest">
        `;

        useFavicons();

        expect(document.querySelector('link[rel="apple-touch-icon"]')?.getAttribute('href')).toBe(
            'https://res.cloudinary.com/demo/apple-touch.jpg'
        );
        expect(document.querySelector('link[rel="manifest"]')?.getAttribute('href')).toBe(
            '/manifest.webmanifest'
        );
    });

    it('leaves the links alone when they already match the theme', () => {
        renderHead('dark');
        const setHref = vi.spyOn(HTMLLinkElement.prototype, 'href', 'set');

        useFavicons();

        expect(setHref).not.toHaveBeenCalled();
    });
});
