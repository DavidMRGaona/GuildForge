<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Bell } from '@lucide/vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import SiteNotificationEntry from '@/components/notifications/SiteNotificationEntry.vue';
import { usePagination } from '@/composables/usePagination';
import { MAIL_TAB } from '@/composables/useProfileTabs';
import { useRoutes } from '@/composables/useRoutes';
import type { SiteNotificationsPageProps } from '@/types/siteNotifications';

/**
 * Every notice of the person (core#491), newest first, 20 per page, with «Marcar todo como
 * leído» and a link to choose which ones they get. Each notice's menu marks it read or unread
 * again or hides it (core#670); the page reloads its props in place and keeps the keyboard focus.
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

function postRowAction(id: string, url: string, done: string, after?: () => void): void {
    if (busyId.value !== null) {
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

function hideNotice(id: string, index: number): void {
    postRowAction(id, routes.notifications.hide(id), t('notifications.actions.hidden'), () => {
        void nextTick(() => {
            const links = Array.from(
                list.value?.querySelectorAll<HTMLElement>('[data-testid="site-notification"]') ?? []
            );
            (links[index] ?? links[index - 1])?.focus();
        });
    });
}
const preferencesUrl = `${routes.profile}?tab=${MAIL_TAB}`;

function markAllRead(): void {
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
                <h1 class="text-3xl font-bold tracking-tight text-base-primary">
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
        <ul
            v-if="props.notifications.data.length > 0"
            ref="list"
            class="space-y-1 rounded-lg border border-default bg-surface p-1"
        >
            <li v-for="(item, index) in props.notifications.data" :key="item.id">
                <SiteNotificationEntry
                    :item="item"
                    :busy="busyId === item.id"
                    @toggle-read="toggleRead(item.id, item.unread)"
                    @hide="hideNotice(item.id, index)"
                />
            </li>
        </ul>

        <div
            v-else
            class="rounded-lg border border-default bg-surface p-10 text-center"
            data-testid="site-notifications-empty"
        >
            <Bell class="mx-auto h-10 w-10 text-base-muted" aria-hidden="true" />
            <p class="mt-3 text-base font-medium text-base-primary">
                {{ t('notifications.empty') }}
            </p>
            <p class="mt-1 text-sm text-base-secondary">{{ t('notifications.emptyHint') }}</p>
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
