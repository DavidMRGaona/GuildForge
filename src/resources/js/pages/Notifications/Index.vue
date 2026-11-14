<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Bell } from '@lucide/vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import SiteNotificationEntry from '@/components/notifications/SiteNotificationEntry.vue';
import { usePagination } from '@/composables/usePagination';
import { MAIL_TAB } from '@/composables/useProfileTabs';
import { useNow } from '@/composables/useNow';
import { useRoutes } from '@/composables/useRoutes';
import type { SiteNotificationsPageProps } from '@/types/siteNotifications';
import { groupByNoticeDay } from '@/utils/noticeDays';

/**
 * Every notice of the person (core#491), newest first, 20 per page, with «Marcar todo como
 * leído» and a link to choose which ones they get. Each notice's menu marks it read or unread
 * again or hides it (core#670); the page reloads its props in place and keeps the keyboard focus.
 * The notices go under «Hoy», «Ayer», «Esta semana» and «Antes» in the display zone, and a filter
 * lists only the unread ones (`?filtro=sin-leer`, kept by the pagination links; core#671).
 */
const props = defineProps<SiteNotificationsPageProps>();

const { t } = useI18n();
const routes = useRoutes();

const {
    firstItemNumber,
    lastItemNumber,
    hasPagination,
    canGoPrev,
    canGoNext,
    isNavigating,
    handlePrev,
    handleNext,
} = usePagination(() => props.notifications);

const marking = ref(false);
const busyId = ref<string | null>(null);
const liveMessage = ref('');
const list = useTemplateRef<HTMLElement>('list');
const heading = useTemplateRef<HTMLElement>('heading');

function postRowAction(id: string, url: string, done: string, after?: () => void): void {
    // One change at a time, «Marcar todo como leído» included
    if (busyId.value !== null || marking.value) {
        return;
    }

    busyId.value = id;
    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                liveMessage.value = done;
                after?.();
            },
            onError: () => {
                liveMessage.value = t('notifications.actions.error');
            },
            onFinish: () => {
                busyId.value = null;
            },
        }
    );
}

function toggleRead(id: string, isUnread: boolean): void {
    postRowAction(
        id,
        isUnread ? routes.notifications.read(id) : routes.notifications.unread(id),
        isUnread ? t('notifications.actions.markedRead') : t('notifications.actions.markedUnread')
    );
}

/**
 * Hide a notice and give the focus to the one now in its place. The last one of a later page
 * leaves it empty: go back a page. With nothing left, the focus goes to the page's title.
 */
function hideNotice(id: string, index: number): void {
    postRowAction(id, routes.notifications.hide(id), t('notifications.actions.hidden'), () => {
        if (props.notifications.data.length === 0 && props.notifications.links.prev !== null) {
            router.visit(props.notifications.links.prev);

            return;
        }

        void nextTick(() => {
            const links = Array.from(
                list.value?.querySelectorAll<HTMLElement>('[data-testid="site-notification"]') ?? []
            );
            (links[index] ?? links[index - 1] ?? heading.value)?.focus();
        });
    });
}
const preferencesUrl = `${routes.profile}?tab=${MAIL_TAB}`;
const unreadUrl = `${routes.notifications.index}?filtro=sin-leer`;
const onlyUnread = computed(() => props.filter === 'unread');

const now = useNow();
const groups = computed(() => groupByNoticeDay(props.notifications.data, now.value));

function markAllRead(): void {
    if (busyId.value !== null || marking.value) {
        return;
    }

    router.post(
        routes.notifications.readAll,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                marking.value = true;
            },
            onFinish: () => {
                marking.value = false;
            },
        }
    );
}
</script>

<template>
    <div data-testid="page-band" class="page-band">
        <div
            class="mx-auto flex max-w-3xl flex-wrap items-end justify-between gap-4 px-4 py-6 sm:px-6 lg:px-8"
        >
            <div>
                <h1
                    ref="heading"
                    tabindex="-1"
                    class="text-3xl font-bold tracking-tight text-base-primary focus:outline-none"
                >
                    {{ t('notifications.title') }}
                </h1>
                <p class="mt-2 text-base text-base-secondary">
                    {{
                        props.unread > 0
                            ? t(
                                  'notifications.unreadSummary',
                                  { count: props.unread },
                                  props.unread
                              )
                            : t('notifications.allCaughtUp')
                    }}
                </p>
            </div>
            <BaseButton
                v-if="props.unread > 0"
                variant="secondary"
                :loading="marking"
                @click="markAllRead"
            >
                {{ t('notifications.markAllRead') }}
            </BaseButton>
        </div>
    </div>

    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <span class="sr-only" role="status" aria-live="polite">{{ liveMessage }}</span>

        <nav
            :aria-label="t('notifications.filter.label')"
            class="mb-6 inline-flex rounded-lg border border-default bg-surface p-1"
            data-testid="site-notifications-filter"
        >
            <Link
                :href="routes.notifications.index"
                :aria-current="onlyUnread ? undefined : 'page'"
                class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                :class="
                    onlyUnread
                        ? 'text-base-secondary hover:text-base-primary'
                        : 'bg-muted text-base-primary'
                "
            >
                {{ t('notifications.filter.all') }}
            </Link>
            <Link
                :href="unreadUrl"
                :aria-current="onlyUnread ? 'page' : undefined"
                class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                :class="
                    onlyUnread
                        ? 'bg-muted text-base-primary'
                        : 'text-base-secondary hover:text-base-primary'
                "
            >
                {{ t('notifications.filter.unread') }}
            </Link>
        </nav>

        <div v-if="props.notifications.data.length > 0" ref="list" class="space-y-6">
            <section
                v-for="group in groups"
                :key="`${group.day}-${group.entries[0]?.index}`"
                :aria-labelledby="`notices-day-${group.day}-${group.entries[0]?.index}`"
                data-testid="site-notifications-day"
            >
                <h2
                    :id="`notices-day-${group.day}-${group.entries[0]?.index}`"
                    class="mb-2 px-1 text-sm font-semibold text-base-secondary"
                >
                    {{ t(`notifications.days.${group.day}`) }}
                </h2>
                <ul class="space-y-1 rounded-lg border border-default bg-surface p-1">
                    <li v-for="entry in group.entries" :key="entry.item.id">
                        <SiteNotificationEntry
                            :item="entry.item"
                            :busy="busyId === entry.item.id"
                            @toggle-read="toggleRead(entry.item.id, entry.item.unread)"
                            @hide="hideNotice(entry.item.id, entry.index)"
                        />
                    </li>
                </ul>
            </section>
        </div>

        <div
            v-else
            class="rounded-lg border border-default bg-surface p-10 text-center"
            data-testid="site-notifications-empty"
        >
            <Bell class="mx-auto h-10 w-10 text-base-muted" aria-hidden="true" />
            <p class="mt-3 text-base font-medium text-base-primary">
                {{ onlyUnread ? t('notifications.emptyUnread') : t('notifications.empty') }}
            </p>
            <p class="mt-1 text-sm text-base-secondary">
                {{ onlyUnread ? t('notifications.allCaughtUp') : t('notifications.emptyHint') }}
            </p>
        </div>

        <div
            v-if="hasPagination"
            class="mt-8 flex flex-wrap items-center justify-between gap-4 border-t border-default pt-6"
        >
            <p class="text-sm text-base-secondary">
                {{ t('common.showing') }}
                <span class="font-medium">{{ firstItemNumber }}</span>
                -
                <span class="font-medium">{{ lastItemNumber }}</span>
                {{ t('common.of') }}
                <span class="font-medium">{{ props.notifications.meta.total }}</span>
            </p>

            <div class="flex gap-2">
                <BaseButton
                    variant="secondary"
                    :disabled="!canGoPrev"
                    :loading="isNavigating"
                    @click="handlePrev"
                >
                    {{ t('common.previous') }}
                </BaseButton>
                <BaseButton
                    variant="secondary"
                    :disabled="!canGoNext"
                    :loading="isNavigating"
                    @click="handleNext"
                >
                    {{ t('common.next') }}
                </BaseButton>
            </div>
        </div>

        <p class="mt-6 text-sm text-base-secondary">
            <Link :href="preferencesUrl" class="font-medium text-primary hover:underline">
                {{ t('notifications.choose') }}
            </Link>
        </p>
    </div>
</template>
