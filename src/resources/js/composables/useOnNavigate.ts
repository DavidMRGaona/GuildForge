import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Runs `callback` whenever the visited URL changes.
 *
 * The site layout persists across visits, so its components are not reset by a new page: a menu
 * left open (back button, a link outside the menu, a redirect after a form) or a message about
 * the previous page would stay. Reloads of the same URL (partial reloads, polling, a form that
 * redirects back) leave them as they are.
 */
export function useOnNavigate(callback: () => void): void {
    const page = usePage();

    watch(
        () => page.url,
        () => callback()
    );
}
