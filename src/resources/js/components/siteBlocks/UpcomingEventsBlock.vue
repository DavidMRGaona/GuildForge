<script setup lang="ts">
import { computed, inject } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { FOOTER_BLOCK_TITLE_ID } from '@/components/siteBlocks/footerBlockTitle';
import type { FooterUpcomingEvent } from '@/types/footerBlocks';
import { VENUE_TIMEZONE } from '@/utils/datetime';

/**
 * Footer block `core.upcoming_events`: the next published events, already chosen by the server
 * (core#698). Dates are drawn in the site's display zone, the one the server used for «today».
 */
const props = defineProps<{
    events: FooterUpcomingEvent[];
    allUrl: string | null;
}>();

const { t, locale } = useI18n();

// Named after the block's title when it has one
const titleId = inject(FOOTER_BLOCK_TITLE_ID, null);

const linkClasses =
    'text-sm text-neutral-300 transition-colors hover:text-primary dark:text-neutral-400 dark:hover:text-primary';

const formatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: VENUE_TIMEZONE,
        })
);

function formatStart(startDate: string): string {
    const date = new Date(startDate);

    return Number.isNaN(date.getTime()) ? '' : formatter.value.format(date);
}

const hasContent = computed(() => props.events.length > 0 || props.allUrl !== null);
</script>

<template>
    <nav
        v-if="hasContent"
        :aria-labelledby="titleId ?? undefined"
        :aria-label="titleId ? undefined : t('layout.upcomingEvents')"
    >
        <ul v-if="events.length > 0" class="space-y-3">
            <li v-for="event in events" :key="event.id">
                <Link
                    :href="event.url"
                    :class="'block text-sm font-medium text-neutral-100 transition-colors hover:text-primary'"
                >
                    {{ event.title }}
                </Link>
                <p class="mt-0.5 text-xs text-neutral-400">
                    <time :datetime="event.startDate">{{ formatStart(event.startDate) }}</time>
                    <template v-if="event.location"> · {{ event.location }}</template>
                </p>
            </li>
        </ul>
        <p v-else class="text-sm text-neutral-400">{{ t('layout.noUpcomingEvents') }}</p>
        <p v-if="allUrl" :class="events.length > 0 ? 'mt-3' : 'mt-2'">
            <Link :href="allUrl" :class="[linkClasses, 'font-medium underline underline-offset-4']">
                {{ t('layout.allEvents') }}
            </Link>
        </p>
    </nav>
</template>
