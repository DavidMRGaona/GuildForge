<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import luxonPlugin from '@fullcalendar/luxon3';
import { useCalendarLocale } from '@/composables/useCalendarLocale';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import type {
    CalendarOptions,
    EventClickArg,
    EventInput,
    EventSourceFuncArg,
    EventHoveringArg,
    EventMountArg,
} from '@fullcalendar/core';
import type { CalendarEntry } from '@/types/models';
import {
    collapseEntriesByActivityType,
    entryUid,
    expandEntriesPerDay,
    toFullCalendarEvents,
} from '@/utils/calendarEntries';
import EventTooltip from './EventTooltip.vue';

interface Props {
    compact?: boolean;
    showTooltips?: boolean;
    navigateOnClick?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    compact: false,
    showTooltips: false,
    navigateOnClick: true,
});

const emit = defineEmits<{
    eventSelect: [event: CalendarEntry];
    calendarClick: [];
    eventsLoaded: [events: CalendarEntry[]];
}>();

const { t } = useI18n();
const calendarLocale = useCalendarLocale();
const isLoading = ref(false);
const error = ref<string | null>(null);
const eventsCache = ref<Map<string, CalendarEntry>>(new Map());

// Tooltip state
const tooltipEvent = ref<CalendarEntry | null>(null);
const tooltipX = ref(0);
const tooltipY = ref(0);
const tooltipVisible = ref(false);

const fetchEvents = async (
    info: EventSourceFuncArg,
    successCallback: (events: EventInput[]) => void,
    failureCallback: (error: Error) => void
): Promise<void> => {
    isLoading.value = true;
    error.value = null;

    try {
        const params = new URLSearchParams({
            start: info.startStr,
            end: info.endStr,
        });

        const response = await fetch(`/eventos/calendario?${params}`);
        if (!response.ok) {
            throw new Error('Failed to fetch events');
        }

        const data: CalendarEntry[] = await response.json();

        // Cache entries (keyed by a source-prefixed uid) for tooltip/detail panel access.
        // In compact mode, multi-day entries are expanded into one synthetic, single-day
        // id per covered day (see expandEntriesPerDay) so every day gets a dot; those
        // synthetic ids must still resolve back to the ORIGINAL entry (real start/end),
        // not the single-day placeholder, so the tooltip/click/aria-label always reflect
        // the entry's real duration.
        eventsCache.value.clear();
        if (props.compact) {
            data.forEach((entry) => {
                expandEntriesPerDay([entry]).forEach((piece) => {
                    eventsCache.value.set(entryUid(piece), entry);
                });
            });
        } else {
            data.forEach((entry) => {
                eventsCache.value.set(entryUid(entry), entry);
            });
        }

        // Emit the full, unexpanded/uncollapsed set for parent component to handle
        emit('eventsLoaded', data);

        // Compact (widget) view: expand multi-day entries into one marker per covered
        // day, then collapse same-day, multi-type entries down to one dot per type
        const displayedEntries = props.compact
            ? collapseEntriesByActivityType(expandEntriesPerDay(data))
            : data;

        successCallback(toFullCalendarEvents(displayedEntries));
    } catch (e) {
        error.value = t('calendar.error');
        console.error('Error fetching calendar events:', e);
        failureCallback(e instanceof Error ? e : new Error('Unknown error'));
    } finally {
        isLoading.value = false;
    }
};

const handleEventClick = (info: EventClickArg): void => {
    info.jsEvent.preventDefault();

    const cachedEvent = eventsCache.value.get(info.event.id);
    if (cachedEvent) {
        emit('eventSelect', cachedEvent);
    }

    if (props.navigateOnClick && info.event.url) {
        router.visit(info.event.url);
    }
};

const handleDateClick = (): void => {
    if (props.compact) {
        emit('calendarClick');
    }
};

const handleMouseEnter = (info: EventHoveringArg): void => {
    if (!props.showTooltips) return;

    const event = eventsCache.value.get(info.event.id);
    if (event) {
        tooltipEvent.value = event;
        tooltipX.value = info.jsEvent.clientX;
        tooltipY.value = info.jsEvent.clientY;
        tooltipVisible.value = true;
    }
};

const handleMouseLeave = (): void => {
    tooltipVisible.value = false;
};

/**
 * Give every rendered event a real accessible name.
 *
 * Compact (widget) mode hides the visible title/time via CSS to keep the
 * dot-only layout, which also removes them from the accessibility tree.
 * Non-event activity types (e.g. game tables) are only distinguished from
 * core events by color, so keyboard/screen-reader users need a text
 * alternative independent of both the hidden title and the hover-only
 * tooltip.
 */
const handleEventDidMount = (info: EventMountArg): void => {
    const entry = eventsCache.value.get(info.event.id);
    if (!entry) return;

    const label =
        entry.sourceType === 'event'
            ? entry.title
            : t('calendar.entryAriaLabel', { source: entry.sourceLabel, title: entry.title });

    info.el.setAttribute('aria-label', label);
};

const calendarOptions = computed<CalendarOptions>(() => {
    const baseOptions: CalendarOptions = {
        plugins: [dayGridPlugin, interactionPlugin, luxonPlugin],
        initialView: 'dayGridMonth',
        // Named timezone support comes from luxonPlugin; without it FullCalendar
        // would place entries on the visitor's local day instead of the venue's.
        timeZone: VENUE_TIMEZONE,
        locale: calendarLocale.value,
        events: fetchEvents,
        eventClick: handleEventClick,
        eventDidMount: handleEventDidMount,
        dateClick: handleDateClick,
        headerToolbar: props.compact
            ? {
                  left: 'prev',
                  center: 'title',
                  right: 'next',
              }
            : {
                  left: 'prev,next today',
                  center: 'title',
                  right: '',
              },
        buttonText: {
            today: t('calendar.today'),
        },
        height: props.compact ? 'auto' : 'auto',
        fixedWeekCount: !props.compact,
        eventDisplay: props.compact ? 'list-item' : 'block',
        loading: (isLoadingArg: boolean) => {
            isLoading.value = isLoadingArg;
        },
    };

    if (props.showTooltips) {
        baseOptions.eventMouseEnter = handleMouseEnter;
        baseOptions.eventMouseLeave = handleMouseLeave;
    }

    return baseOptions;
});

// Update tooltip position on mouse move when visible
const handleGlobalMouseMove = (event: MouseEvent): void => {
    if (tooltipVisible.value) {
        tooltipX.value = event.clientX;
        tooltipY.value = event.clientY;
    }
};

watch(tooltipVisible, (visible) => {
    if (visible) {
        document.addEventListener('mousemove', handleGlobalMouseMove);
    } else {
        document.removeEventListener('mousemove', handleGlobalMouseMove);
    }
});
</script>

<template>
    <div :class="['event-calendar', { 'event-calendar--compact': compact }]">
        <div v-if="error" role="alert" class="mb-4 rounded-lg bg-error-light p-4 text-center">
            <p class="text-sm font-medium text-error">
                {{ error }}
            </p>
        </div>

        <div class="relative">
            <div
                v-if="isLoading"
                class="absolute inset-0 z-10 flex items-center justify-center bg-surface/70"
            >
                <div class="text-center">
                    <div
                        class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-primary-500 border-t-transparent"
                    ></div>
                    <p class="mt-2 text-sm text-base-secondary">
                        {{ t('calendar.loading') }}
                    </p>
                </div>
            </div>

            <FullCalendar :options="calendarOptions" />
        </div>

        <!-- Tooltip -->
        <EventTooltip
            v-if="showTooltips"
            :event="tooltipEvent"
            :x="tooltipX"
            :y="tooltipY"
            :visible="tooltipVisible"
        />
    </div>
</template>
