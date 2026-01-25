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
 * If custom favicons are configured in settings, those are used.
 * Otherwise, falls back to the static favicons in /favicons/{theme}/.
 */
export function useFavicons(): void {
    const appStore = useAppStore();
    const page = usePage();

    function updateFavicons(isDark: boolean): void {
        const theme = isDark ? 'dark' : 'light';
        const favicons = page.props.favicons as FaviconSettings | undefined;

        // Use custom favicon if configured, otherwise use static defaults
        const customFavicon = isDark ? favicons?.dark : favicons?.light;

        if (customFavicon) {
            // Custom favicon from settings - use for all icon links
            updateLinkHref('link[rel="icon"][sizes="32x32"]', customFavicon);
            updateLinkHref('link[rel="icon"][sizes="16x16"]', customFavicon);
            updateLinkHref('link[rel="shortcut icon"]', customFavicon);
        } else {
            // Use static default favicons
            const basePath = `/favicons/${theme}`;
            updateLinkHref('link[rel="icon"][sizes="32x32"]', `${basePath}/favicon-32x32.png`);
            updateLinkHref('link[rel="icon"][sizes="16x16"]', `${basePath}/favicon-16x16.png`);
            updateLinkHref('link[rel="shortcut icon"]', `${basePath}/favicon.ico`);
        }

        // Apple touch icon and manifest always use static files (or could be extended)
        const basePath = `/favicons/${theme}`;
        updateLinkHref('link[rel="apple-touch-icon"]', `${basePath}/apple-touch-icon.png`);
        updateLinkHref('link[rel="manifest"]', `${basePath}/site.webmanifest`);
    }

    // Watch for theme changes and update favicons accordingly
    watch(() => appStore.isDarkMode, updateFavicons, { immediate: true });
}
