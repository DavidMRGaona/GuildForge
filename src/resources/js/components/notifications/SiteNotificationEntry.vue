<script setup lang="ts">
import { computed } from 'vue';
import type { DeepReadonly } from 'vue';
import { useI18n } from 'vue-i18n';
import type { SiteNotificationItem } from '@/types/siteNotifications';
import { resolveSiteNotificationIcon } from '@/utils/icons';
import { formatRelativeTime } from '@/utils/relativeTime';

/**
 * One row of the notification centre, in the bell's list or on the «Avisos» page. The whole row
 * is a link through the core (`/avisos/{id}`), which marks it read and then goes where it leads:
 * a full visit, so a link to a message (`#m42`) lands on it. Unread rows are marked with a dot and
 * say so to screen readers.
 */
interface Props {
    item: DeepReadonly<SiteNotificationItem>;
    /** The bell's list: one line of excerpt, smaller paddings. */
    compact?: boolean;
}

const props = withDefaults(defineProps<Props>(), { compact: false });

const { t, locale } = useI18n();

const icon = computed(() => resolveSiteNotificationIcon(props.item.icon));
const when = computed(() => formatRelativeTime(props.item.notifiedAt, locale.value));
const others = computed(() => props.item.count - 1);
</script>

<template>
    <a
        :href="item.href"
        class="flex gap-3 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
        :class="[
            compact ? 'px-3 py-2.5' : 'px-4 py-3',
            item.unread
                ? 'bg-primary-50 hover:bg-primary-100 dark:bg-primary-900/20 dark:hover:bg-primary-900/30'
                : 'hover:bg-muted',
        ]"
        :data-unread="item.unread ? 'true' : 'false'"
        data-testid="site-notification"
    >
        <span
            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary dark:bg-primary-900/40"
            aria-hidden="true"
        >
            <component :is="icon" class="h-4 w-4" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm text-base-primary" :class="{ 'font-semibold': item.unread }">
                <span v-if="item.unread" class="sr-only">{{ t('notifications.unreadLabel') }}</span>
                {{ item.title }}
            </span>
            <span
                v-if="item.body"
                class="mt-0.5 block text-sm text-base-secondary"
                :class="compact ? 'truncate' : 'line-clamp-2'"
            >
                {{ item.body }}
            </span>
            <span class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-base-muted">
                <time v-if="item.notifiedAt" :datetime="item.notifiedAt">{{ when }}</time>
                <span v-if="others > 0" data-testid="site-notification-count">
                    {{ t('notifications.moreInGroup', { count: others }, others) }}
                </span>
            </span>
        </span>
        <span
            v-if="item.unread"
            class="mt-2 h-2 w-2 shrink-0 rounded-full bg-primary-600 dark:bg-primary-400"
            aria-hidden="true"
        />
    </a>
</template>
