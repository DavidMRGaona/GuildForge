<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, useTemplateRef, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Bell, Settings2 } from '@lucide/vue';
import SiteNotificationEntry from '@/components/notifications/SiteNotificationEntry.vue';
import { useOnNavigate } from '@/composables/useOnNavigate';
import { MAIL_TAB } from '@/composables/useProfileTabs';
import { useRoutes } from '@/composables/useRoutes';
import { useSiteNotifications } from '@/composables/useSiteNotifications';

/**
 * The bell of the header (core#491): the unread counter (up to «9+») and, on click, the latest
 * notices with «Marcar todo como leído», a shortcut to the notice settings (core#669) and a link to
 * the «Avisos» page. A disclosure: Tab moves
 * through the list, the arrows go from notice to notice, Escape closes it and gives the focus back
 * to the bell. A rise of the counter is announced to screen readers.
 */
interface Props {
    /** Unique per bell on the page (the desktop bar and the narrow header each have one). */
    panelId?: string;
    /**
     * The bell of the narrow header sits left of the menu button (40 px and a 4 px gap): the list
     * then reaches past the bell to the edge of the header, or on a phone it would leave the screen.
     */
    besideMenuButton?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    panelId: 'site-notifications-panel',
    besideMenuButton: false,
});

const { t } = useI18n();
const routes = useRoutes();
const notifications = useSiteNotifications();
const { unread, items, loading, failed } = notifications;

const isOpen = ref(false);
const marking = ref(false);
// The notice whose menu choice the server is answering (core#670)
const busyId = ref<string | null>(null);
const liveMessage = ref('');
const root = useTemplateRef<HTMLElement>('root');
const button = useTemplateRef<HTMLButtonElement>('button');
const list = useTemplateRef<HTMLElement>('list');

const settingsUrl = `${routes.profile}?tab=${MAIL_TAB}`;
const headingId = computed(() => `${props.panelId}-heading`);
const badge = computed(() => (unread.value > 9 ? '9+' : String(unread.value)));
const buttonLabel = computed(() =>
    unread.value > 0
        ? t('notifications.bellWithUnread', { count: unread.value }, unread.value)
        : t('notifications.bell')
);

// Only a rise is news: opening the list or marking everything read is the person's own doing
watch(unread, (count, previous) => {
    if (count > previous) {
        liveMessage.value = t('notifications.liveUnread', { count }, count);
    }
});

let stopPolling: (() => void) | null = null;

onMounted(() => {
    stopPolling = notifications.startPolling();
    document.addEventListener('click', onClickOutside);
});

onUnmounted(() => {
    stopPolling?.();
    document.removeEventListener('click', onClickOutside);
});

useOnNavigate(() => {
    isOpen.value = false;
});

function onClickOutside(event: MouseEvent): void {
    const target = event.target;
    if (isOpen.value && target instanceof Node && root.value && !root.value.contains(target)) {
        isOpen.value = false;
    }
}

async function toggle(): Promise<void> {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        await notifications.loadRecent();
    }
}

function close(returnFocus: boolean): void {
    isOpen.value = false;
    if (returnFocus) {
        void nextTick(() => button.value?.focus());
    }
}

/** Leaving the bell with Tab closes the list, as with any disclosure that floats over the page. */
function onFocusOut(event: FocusEvent): void {
    const next = event.relatedTarget;
    if (isOpen.value && next instanceof Node && root.value && !root.value.contains(next)) {
        isOpen.value = false;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && isOpen.value) {
        event.preventDefault();
        close(true);

        return;
    }

    if (!isOpen.value || (event.key !== 'ArrowDown' && event.key !== 'ArrowUp')) {
        return;
    }

    const links = Array.from(list.value?.querySelectorAll<HTMLElement>('a') ?? []);
    if (links.length === 0) {
        return;
    }

    event.preventDefault();
    const current = links.indexOf(document.activeElement as HTMLElement);
    const step = event.key === 'ArrowDown' ? 1 : -1;
    const next =
        current === -1
            ? step === 1
                ? 0
                : links.length - 1
            : (current + step + links.length) % links.length;
    links[next]?.focus();
}

async function toggleRead(id: string, isUnread: boolean): Promise<void> {
    if (busyId.value !== null || marking.value) {
        return;
    }

    busyId.value = id;
    const done = await notifications.setRead(id, isUnread);
    busyId.value = null;
    liveMessage.value = !done
        ? t('notifications.actions.error')
        : isUnread
          ? t('notifications.actions.markedRead')
          : t('notifications.actions.markedUnread');
}

/** Hide a notice and give the focus to the one now in its place, so the keyboard is not lost. */
async function hideNotice(id: string, index: number): Promise<void> {
    if (busyId.value !== null || marking.value) {
        return;
    }

    busyId.value = id;
    const done = await notifications.hide(id);
    busyId.value = null;
    liveMessage.value = done ? t('notifications.actions.hidden') : t('notifications.actions.error');
    if (!done) {
        return;
    }

    await nextTick();
    const links = Array.from(
        list.value?.querySelectorAll<HTMLElement>('[data-testid="site-notification"]') ?? []
    );
    (links[index] ?? links[index - 1] ?? button.value)?.focus();
}

async function markAllRead(): Promise<void> {
    if (marking.value || busyId.value !== null) {
        return;
    }

    marking.value = true;
    const done = await notifications.markAllRead();
    marking.value = false;
    liveMessage.value = done ? t('notifications.allRead') : t('notifications.markAllReadError');
}
</script>

<template>
    <div ref="root" class="relative" @keydown="onKeydown" @focusout="onFocusOut">
        <button
            ref="button"
            type="button"
            :aria-label="buttonLabel"
            :aria-expanded="isOpen"
            :aria-controls="panelId"
            class="relative p-2 rounded-md text-base-secondary hover:bg-muted hover:text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            data-testid="notification-bell"
            @click="toggle"
        >
            <Bell class="h-5 w-5" aria-hidden="true" />
            <span
                v-if="unread > 0"
                class="absolute -right-0.5 -top-0.5 flex h-[1.125rem] min-w-[1.125rem] items-center justify-center rounded-full bg-primary-600 px-1 text-[0.6875rem] font-semibold leading-none text-white dark:bg-primary-500"
                aria-hidden="true"
                data-testid="notification-badge"
                >{{ badge }}</span
            >
        </button>

        <!-- Rises of the counter (polling) and the result of «Marcar todo como leído» -->
        <span class="sr-only" role="status" aria-live="polite">{{ liveMessage }}</span>

        <div
            v-if="isOpen"
            :id="panelId"
            role="region"
            :aria-labelledby="headingId"
            class="absolute z-50 mt-2 w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-lg border border-default bg-surface shadow-lg"
            :class="besideMenuButton ? '-right-11' : 'right-0'"
        >
            <div class="flex items-center justify-between gap-3 border-b border-default px-4 py-3">
                <h2 :id="headingId" class="text-sm font-semibold text-base-primary">
                    {{ t('notifications.title') }}
                </h2>
                <div class="flex items-center gap-2">
                    <button
                        v-if="unread > 0"
                        type="button"
                        class="rounded text-sm font-medium text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-50"
                        :disabled="marking"
                        @click="markAllRead"
                    >
                        {{ t('notifications.markAllRead') }}
                    </button>
                    <Link
                        :href="settingsUrl"
                        :aria-label="t('notifications.choose')"
                        :title="t('notifications.choose')"
                        class="rounded-md p-1 text-base-secondary hover:bg-muted hover:text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        data-testid="notification-settings"
                    >
                        <Settings2 class="h-4 w-4" aria-hidden="true" />
                    </Link>
                </div>
            </div>

            <div ref="list" class="max-h-[min(28rem,70vh)] overflow-y-auto p-1">
                <p
                    v-if="loading && items.length === 0"
                    class="px-3 py-6 text-center text-sm text-base-secondary"
                >
                    {{ t('notifications.loading') }}
                </p>
                <div
                    v-else-if="failed && items.length === 0"
                    class="px-3 py-6 text-center text-sm text-base-secondary"
                >
                    <p>{{ t('notifications.loadError') }}</p>
                    <button
                        type="button"
                        class="mt-2 font-medium text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        @click="notifications.loadRecent()"
                    >
                        {{ t('notifications.retry') }}
                    </button>
                </div>
                <p
                    v-else-if="items.length === 0"
                    class="px-3 py-6 text-center text-sm text-base-secondary"
                >
                    {{ t('notifications.empty') }}
                </p>
                <ul v-else class="space-y-0.5" :aria-label="t('notifications.latest')">
                    <li v-for="(item, index) in items" :key="item.id">
                        <SiteNotificationEntry
                            :item="item"
                            compact
                            :busy="busyId === item.id"
                            @toggle-read="toggleRead(item.id, item.unread)"
                            @hide="hideNotice(item.id, index)"
                        />
                    </li>
                </ul>
            </div>

            <div class="border-t border-default px-4 py-2.5 text-center">
                <Link
                    :href="routes.notifications.index"
                    class="text-sm font-medium text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                >
                    {{ t('notifications.seeAll') }}
                </Link>
            </div>
        </div>
    </div>
</template>
