import { ref, onMounted, onUnmounted, type Ref } from 'vue';

/**
 * Composable for reactive media query detection
 *
 * @param query - Media query string (e.g., '(min-width: 1024px)')
 * @returns Reactive ref that tracks whether the media query matches
 *
 * @example
 * ```ts
 * const isDesktop = useMediaQuery('(min-width: 1024px)');
 * const isMobile = useMediaQuery('(max-width: 767px)');
 * const prefersDark = useMediaQuery('(prefers-color-scheme: dark)');
 * ```
 */
export function useMediaQuery(query: string): Ref<boolean> {
    // Handle SSR gracefully - return false if window is not defined
    const matches = ref<boolean>(false);

    // Only proceed if running in browser
    if (typeof window === 'undefined') {
        return matches;
    }

    let mediaQueryList: MediaQueryList | null = null;

    const updateMatches = (event: MediaQueryListEvent | MediaQueryList): void => {
        matches.value = event.matches;
    };

    onMounted(() => {
        // Create MediaQueryList for efficient detection
        mediaQueryList = window.matchMedia(query);

        // Set initial value
        matches.value = mediaQueryList.matches;

        // Listen for changes
        // Using addEventListener for better browser compatibility
        mediaQueryList.addEventListener('change', updateMatches);
    });

    onUnmounted(() => {
        // Clean up event listener to prevent memory leaks
        if (mediaQueryList) {
            mediaQueryList.removeEventListener('change', updateMatches);
        }
    });

    return matches;
}
