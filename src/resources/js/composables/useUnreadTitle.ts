import { useHead } from '@unhead/vue';
import { useSiteNotifications } from '@/composables/useSiteNotifications';

/** The unread count as the bell shows it: up to «9+». */
export function unreadBadge(count: number): string {
    return count > 9 ? '9+' : String(count);
}

/** «(3) Eventos · Sitio»: the title with the unread count before it, or as it is with none. */
export function withUnreadCount(title: string | undefined, count: number): string {
    const base = title ?? '';

    if (count <= 0) {
        return base;
    }

    return base === '' ? `(${unreadBadge(count)})` : `(${unreadBadge(count)}) ${base}`;
}

/**
 * The unread notices in the tab title (core#672), so someone with the site open in another tab
 * sees that something arrived. It follows the counter the bell already keeps (shared props,
 * polling or real time), with no request of its own, and wraps whatever title the page sets
 * (a title template), so Inertia visits never stack it. `enabled` is where the bell is shown:
 * signed in and not waiting for an email verification.
 */
export function useUnreadTitle(enabled: () => boolean): void {
    const { unread } = useSiteNotifications();

    useHead(() => {
        const count = enabled() ? unread.value : 0;

        return {
            titleTemplate:
                count > 0 ? (title?: string): string => withUnreadCount(title, count) : null,
        };
    });
}
