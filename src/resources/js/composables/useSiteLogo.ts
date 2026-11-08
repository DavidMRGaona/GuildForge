import { computed, ref } from 'vue';
import type { ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useAppStore } from '@/stores/useAppStore';

export interface SiteLogo {
    /** URL of the logo for the current theme, or null when there is none or it failed to load */
    logo: ComputedRef<string | null>;
    /** Call from the image's error event so the caller falls back to the site name */
    markFailed: () => void;
}

/**
 * The site logo matching the current theme (siteLogoLight / siteLogoDark shared props).
 */
export function useSiteLogo(): SiteLogo {
    const page = usePage();
    const appStore = useAppStore();

    const themeLogo = computed((): string | null => {
        const url = appStore.isDarkMode ? page.props.siteLogoDark : page.props.siteLogoLight;

        return typeof url === 'string' && url !== '' ? url : null;
    });

    // A failure only counts for the URL that failed: switching theme tries the other logo
    const failedUrl = ref<string | null>(null);

    const logo = computed((): string | null =>
        themeLogo.value !== null && themeLogo.value !== failedUrl.value ? themeLogo.value : null
    );

    function markFailed(): void {
        failedUrl.value = themeLogo.value;
    }

    return { logo, markFailed };
}
