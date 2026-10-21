<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/vue3/daygrid';
import interactionPlugin from '@fullcalendar/vue3/interaction';
import { useCalendarLocale } from '@/composables/useCalendarLocale';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import { calendarClassOptions, calendarThemePlugin } from '@/utils/calendarTheme';
import type {
    CalendarOptions,
    EventClickInfo,
    EventDisplayInfo,
    EventInput,
    EventSourceFuncInfo,
    EventHoveringInfo,
    MountInfo,
} from '@fullcalendar/vue3';
import type { CalendarEntry } from '@/types/models';
import {
    collapseEntriesByActivityType,
    entryUid,
    expandEntriesPerDay,
    toCompactDayEvents,
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
    info: EventSourceFuncInfo,
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

        successCallback(
            props.compact
                ? toCompactDayEvents(displayedEntries)
                : toFullCalendarEvents(displayedEntries)
        );
    } catch (e) {
        error.value = t('calendar.error');
        console.error('Error fetching calendar events:', e);
        failureCallback(e instanceof Error ? e : new Error('Unknown error'));
    } finally {
        isLoading.value = false;
    }
};

/**
 * Uids of the entries a rendered event stands for: the entries of the whole day
 * for a compact day event (see toCompactDayEvents), or the event itself.
 */
const eventEntryUids = (event: {
    id: string;
    extendedProps: Record<string, unknown>;
}): string[] => {
    const uids = event.extendedProps.entryUids;

    return Array.isArray(uids)
        ? uids.filter((uid): uid is string => typeof uid === 'string')
        : [event.id];
};

const entryLabel = (entry: CalendarEntry): string =>
    entry.sourceType === 'event'
        ? entry.title
        : t('calendar.entryAriaLabel', { source: entry.sourceLabel, title: entry.title });

const handleEventClick = (info: EventClickInfo): void => {
    info.jsEvent.preventDefault();

    const [firstUid] = eventEntryUids(info.event);
    const cachedEvent = firstUid !== undefined ? eventsCache.value.get(firstUid) : undefined;
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

const showTooltip = (uid: string, jsEvent: MouseEvent): void => {
    if (!props.showTooltips) return;

    const event = eventsCache.value.get(uid);
    if (event) {
        tooltipEvent.value = event;
        tooltipX.value = jsEvent.clientX;
        tooltipY.value = jsEvent.clientY;
        tooltipVisible.value = true;
    }
};

// A compact day event holds several dots, each with its own tooltip (see the template)
const handleMouseEnter = (info: EventHoveringInfo): void => {
    showTooltip(info.event.id, info.jsEvent);
};

const handleMouseLeave = (): void => {
    tooltipVisible.value = false;
};

/**
 * Give every rendered event a real accessible name.
 *
 * Compact (widget) mode draws one event per day holding only dots, with no
 * visible title or time, so it is named after all the day's entries.
 * Non-event activity types (e.g. game tables) are only distinguished from
 * core events by color, so keyboard/screen-reader users need a text
 * alternative independent of both the missing title and the hover-only
 * tooltip.
 */
const handleEventDidMount = (info: MountInfo<EventDisplayInfo>): void => {
    const labels = eventEntryUids(info.event)
        .map((uid) => eventsCache.value.get(uid))
        .filter((entry): entry is CalendarEntry => entry !== undefined)
        .map(entryLabel);

    if (labels.length > 0) {
        info.el.setAttribute('aria-label', labels.join('; '));
    }
};

const calendarOptions = computed<CalendarOptions>(() => {
    const baseOptions: CalendarOptions = {
        ...calendarClassOptions,
        plugins: [calendarThemePlugin, dayGridPlugin, interactionPlugin],
        initialView: 'dayGridMonth',
        // Without a named timezone FullCalendar would place entries on the
        // visitor's local day instead of the venue's.
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
        buttons: {
            today: { text: t('calendar.today') },
        },
        height: 'auto',
        fixedWeekCount: !props.compact,
        eventDisplay: props.compact ? 'list-item' : 'block',
        loading: (isLoadingArg: boolean) => {
            isLoading.value = isLoadingArg;
        },
    };

    if (props.showTooltips && !props.compact) {
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

            <FullCalendar :options="calendarOptions">
                <template v-if="compact" #eventContent="arg">
                    <span
                        v-for="uid in eventEntryUids(arg.event)"
                        :key="uid"
                        class="calendar-dot"
                        :style="{
                            '--calendar-dot-color': `var(--calendar-entry-${eventsCache.get(uid)?.color ?? 'primary'})`,
                        }"
                        aria-hidden="true"
                        @mouseenter="showTooltip(uid, $event)"
                        @mouseleave="handleMouseLeave"
                    ></span>
                </template>
            </FullCalendar>
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
