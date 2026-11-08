<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { CalendarEntry } from '@/types/models';
import { extractLegend, type CalendarLegendItem } from '@/utils/calendarEntries';
import type { CalendarView } from '@/utils/calendarViews';
import EventCalendar from './EventCalendar.vue';

/**
 * The site calendar (every public source) for module pages: the public API
 * entry, kept narrow so EventCalendar.vue can change without breaking modules.
 * Clicking an entry opens its page.
 */
interface Props {
    /** View shown first: the month grid or the month's list. */
    initialView?: CalendarView;
    /** Views offered as buttons; with fewer than two there are none. The initial view is always in. */
    views?: CalendarView[];
    /** Height in pixels, or 'auto' to grow with the content. */
    height?: number | 'auto';
    /** Whether to list the activity types and their colours below the calendar. */
    legend?: boolean;
}

withDefaults(defineProps<Props>(), {
    initialView: 'month',
    views: () => [],
    height: 'auto',
    legend: true,
});

const { t } = useI18n();
const legendItems = ref<CalendarLegendItem[]>([]);

const handleEventsLoaded = (events: CalendarEntry[]): void => {
    legendItems.value = extractLegend(events);
};
</script>

<template>
    <div>
        <EventCalendar
            :initial-view="initialView"
            :views="views"
            :height="height"
            @events-loaded="handleEventsLoaded"
        />

        <ul
            v-if="legend && legendItems.length > 0"
            class="mt-4 flex list-none flex-wrap items-center gap-x-4 gap-y-2 p-0"
            :aria-label="t('calendar.legendLabel')"
        >
            <li
                v-for="item in legendItems"
                :key="item.sourceType"
                class="flex items-center gap-1.5 text-sm text-base-secondary"
            >
                <span
                    :class="['calendar-legend-dot', `calendar-legend-dot--${item.color}`]"
                    aria-hidden="true"
                ></span>
                {{ item.sourceLabel }}
            </li>
        </ul>
    </div>
</template>
