import { readonly, ref, watch } from 'vue';
import type { DeepReadonly, Ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useRoutes } from '@/composables/useRoutes';
import { csrfHeaders } from '@/utils/csrf';
import type { SiteNotificationItem, SiteNotificationsSharedProps } from '@/types/siteNotifications';

interface SharedProps {
    siteNotifications?: SiteNotificationsSharedProps | null;
}

/** How often the bell asks for the counter while the tab is visible. */
export const POLL_INTERVAL_MS = 60_000;

export interface UseSiteNotificationsReturn {
    /** Unread rows (a group counts once). */
    unread: Readonly<Ref<number>>;
    /** The latest notices, loaded when the list opens. */
    items: Readonly<Ref<DeepReadonly<SiteNotificationItem[]>>>;
    loading: Readonly<Ref<boolean>>;
    /** The last load of the list failed (the counter keeps its last value). */
    failed: Readonly<Ref<boolean>>;
    /** Ask for the counter again (polling, back to the tab). */
    refreshCount: () => Promise<void>;
    /** Load the latest notices and the counter (when the list opens). */
    loadRecent: () => Promise<void>;
    /** Mark every notice read; true when the server did. */
    markAllRead: () => Promise<boolean>;
    /** Start asking every minute and on return to the tab; stops when the last user stops. */
    startPolling: () => () => void;
}

// One state for the whole page: the bell of the desktop bar and the one of the narrow header
// share it, and only one timer asks the server
const unread = ref(0);
const items = ref<SiteNotificationItem[]>([]);
const loading = ref(false);
const failed = ref(false);
let pollers = 0;
let timer: ReturnType<typeof setInterval> | null = null;

function readNumber(value: unknown): number | null {
    return typeof value === 'number' && Number.isFinite(value) && value >= 0
        ? Math.floor(value)
        : null;
}

async function getJson(url: string): Promise<Record<string, unknown>> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    if (!response.ok) {
        throw new Error(`${url} answered ${response.status}`);
    }

    return (await response.json()) as Record<string, unknown>;
}

function onVisibilityChange(routes: ReturnType<typeof useRoutes>): () => void {
    return () => {
        if (document.visibilityState === 'visible') {
            void refresh(routes);
        }
    };
}

async function refresh(routes: ReturnType<typeof useRoutes>): Promise<void> {
    // A hidden tab waits: it asks again when the person comes back
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
        return;
    }

    try {
        const count = readNumber((await getJson(routes.notifications.count)).unread);
        if (count !== null) {
            unread.value = count;
        }
    } catch {
        // Offline, signed out in another tab or rate limited: keep the last count
    }
}

/**
 * The notification centre of the header (core#491): the unread counter, which every page brings
 * in its shared props and the bell refreshes every minute and on return to the tab (a light JSON
 * request, never a page visit), and the latest notices, loaded when the list opens.
 */
export function useSiteNotifications(): UseSiteNotificationsReturn {
    const routes = useRoutes();
    const page = usePage();

    // Each visit brings a fresh count with it (every bell watches: they write the same value)
    watch(
        () => (page.props as SharedProps).siteNotifications?.unread,
        (count) => {
            const value = readNumber(count);
            if (value !== null) {
                unread.value = value;
            }
        },
        { immediate: true }
    );

    async function loadRecent(): Promise<void> {
        loading.value = true;
        try {
            const body = await getJson(routes.notifications.recent);
            items.value = Array.isArray(body.items) ? (body.items as SiteNotificationItem[]) : [];
            const count = readNumber(body.unread);
            if (count !== null) {
                unread.value = count;
            }
            failed.value = false;
        } catch {
            failed.value = true;
        } finally {
            loading.value = false;
        }
    }

    async function markAllRead(): Promise<boolean> {
        try {
            const response = await fetch(routes.notifications.readAll, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return false;
            }
            unread.value = 0;
            items.value = items.value.map((item) => ({ ...item, unread: false }));

            return true;
        } catch {
            return false;
        }
    }

    function startPolling(): () => void {
        const listener = onVisibilityChange(routes);
        pollers++;
        if (pollers === 1) {
            timer = setInterval(() => void refresh(routes), POLL_INTERVAL_MS);
        }
        document.addEventListener('visibilitychange', listener);

        return () => {
            document.removeEventListener('visibilitychange', listener);
            pollers = Math.max(0, pollers - 1);
            if (pollers === 0 && timer !== null) {
                clearInterval(timer);
                timer = null;
            }
        };
    }

    return {
        unread: readonly(unread),
        items: readonly(items) as Readonly<Ref<DeepReadonly<SiteNotificationItem[]>>>,
        loading: readonly(loading),
        failed: readonly(failed),
        refreshCount: () => refresh(routes),
        loadRecent,
        markAllRead,
        startPolling,
    };
}

/** Only for tests: forget the shared state between them. */
export function resetSiteNotificationsState(): void {
    unread.value = 0;
    items.value = [];
    loading.value = false;
    failed.value = false;
    pollers = 0;
    if (timer !== null) {
        clearInterval(timer);
        timer = null;
    }
}
