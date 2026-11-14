import { computed, effectScope, readonly, ref, watch } from 'vue';
import type { ComputedRef, DeepReadonly, EffectScope, Ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useRealtimeChannel } from '@/composables/useRealtimeChannel';
import { useRoutes } from '@/composables/useRoutes';
import { csrfHeaders } from '@/utils/csrf';
import type { SiteNotificationItem, SiteNotificationsSharedProps } from '@/types/siteNotifications';

interface SharedProps {
    siteNotifications?: SiteNotificationsSharedProps | null;
    auth?: { user?: { mustVerifyEmail?: boolean } | null } | null;
}

/** How often the bell asks for the counter while the tab is visible and real time is not live. */
export const POLL_INTERVAL_MS = 60_000;

/** What the account's channel says when a notice is delivered (core#493): nothing but that. */
export const DELIVERED_EVENT = 'site-notification.delivered';

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
    /**
     * Start asking every minute and on return to the tab; stops when the last user stops. With
     * real time live, a delivered notice is heard at once and the minute's question is skipped.
     */
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
// One subscription for the whole page, however many bells it has
let realtimeScope: EffectScope | null = null;
let live = false;

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
 * Whether the page shows the bell (core#491): signed in, not while the account must verify its
 * email (its routes are closed until then), and with the counter in the shared props. The header
 * and the tab title (core#672) ask the same question.
 */
export function useBellVisible(): ComputedRef<boolean> {
    const page = usePage();

    return computed(() => {
        const props = page.props as SharedProps | undefined;
        const user = props?.auth?.user ?? null;

        return user !== null && user.mustVerifyEmail !== true && props?.siteNotifications != null;
    });
}

/**
 * The notification centre of the header (core#491): the unread counter, which every page brings
 * in its shared props and the bell refreshes every minute and on return to the tab (a light JSON
 * request, never a page visit), and the latest notices, loaded when the list opens. With real
 * time (core#493) the account's channel says when a notice arrives and the counter is asked then.
 */
export function useSiteNotifications(): UseSiteNotificationsReturn {
    const routes = useRoutes();
    const page = usePage();

    // Each visit brings a fresh count with it (every bell watches: they write the same value)
    watch(
        // The app root runs before Inertia sets the first page, so props may not exist yet
        () => (page.props as SharedProps | undefined)?.siteNotifications?.unread,
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

    function listenInRealTime(): void {
        realtimeScope = effectScope(true);
        realtimeScope.run(() => {
            const { status } = useRealtimeChannel(
                () => (page.props as SharedProps).siteNotifications?.channel ?? null,
                { [DELIVERED_EVENT]: () => void refresh(routes) }
            );
            watch(status, (now, before) => {
                live = now === 'live';
                // Back after a drop: a notice may have arrived meanwhile
                if (live && before === 'offline') {
                    void refresh(routes);
                }
            });
        });
    }

    function startPolling(): () => void {
        const listener = onVisibilityChange(routes);
        pollers++;
        if (pollers === 1) {
            timer = setInterval(() => {
                if (!live) {
                    void refresh(routes);
                }
            }, POLL_INTERVAL_MS);
            listenInRealTime();
        }
        document.addEventListener('visibilitychange', listener);

        return () => {
            document.removeEventListener('visibilitychange', listener);
            pollers = Math.max(0, pollers - 1);
            if (pollers === 0) {
                if (timer !== null) {
                    clearInterval(timer);
                    timer = null;
                }
                realtimeScope?.stop();
                realtimeScope = null;
                live = false;
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
    realtimeScope?.stop();
    realtimeScope = null;
    live = false;
}
