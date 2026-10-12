import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useAppStore } from '@/stores/useAppStore';
import type { FaviconSettings } from '@/types/inertia';

/**
 * Updates the href attribute of a link element matching the given selector.
 */
function updateLinkHref(selector: string, href: string): void {
    const link = document.querySelector<HTMLLinkElement>(selector);
    if (link) {
        link.href = href;
    }
}

/**
 * Composable to manage dynamic favicon switching based on app theme.
 *
 * This updates favicon links in the document head when the theme changes,
 * allowing favicons to follow the app's theme toggle instead of just
 * responding to OS prefers-color-scheme.
 *
 * If custom favicons are configured in settings, those are used for every
 * icon, the iOS home screen one included (as <x-site-favicons> renders them).
 * Otherwise, falls back to the static favicons in /favicons/{theme}/.
 */
export function useFavicons(): void {
    const appStore = useAppStore();
    const page = usePage();

    function updateFavicons(isDark: boolean): void {
        const basePath = `/favicons/${isDark ? 'dark' : 'light'}`;
        const favicons = page.props.favicons as FaviconSettings | undefined;
        // Either custom favicon beats the GuildForge default (as <x-site-favicons> does)
        const customFavicon = isDark
            ? (favicons?.dark ?? favicons?.light)
            : (favicons?.light ?? favicons?.dark);

        updateLinkHref(
            'link[rel="icon"][sizes="32x32"]',
            customFavicon ?? `${basePath}/favicon-32x32.png`
        );
        updateLinkHref(
            'link[rel="icon"][sizes="16x16"]',
            customFavicon ?? `${basePath}/favicon-16x16.png`
        );
        updateLinkHref('link[rel="shortcut icon"]', customFavicon ?? `${basePath}/favicon.ico`);
        updateLinkHref(
            'link[rel="apple-touch-icon"]',
            customFavicon ?? `${basePath}/apple-touch-icon.png`
        );
        updateLinkHref('link[rel="manifest"]', `${basePath}/site.webmanifest`);
    }

    // Watch for theme changes and update favicons accordingly
    watch(() => appStore.isDarkMode, updateFavicons, { immediate: true });
}
