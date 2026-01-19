<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import type {
    CalendarOptions,
    EventClickArg,
    EventSourceFuncArg,
    EventHoveringArg,
} from '@fullcalendar/core';
import type { CalendarEvent } from '@/types/models';
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
    eventSelect: [event: CalendarEvent];
    calendarClick: [];
    eventsLoaded: [events: CalendarEvent[]];
}>();

const { locale, t } = useI18n();
const isLoading = ref(false);
const error = ref<string | null>(null);
const eventsCache = ref<Map<string, CalendarEvent>>(new Map());

// Tooltip state
const tooltipEvent = ref<CalendarEvent | null>(null);
const tooltipX = ref(0);
const tooltipY = ref(0);
const tooltipVisible = ref(false);

const fetchEvents = async (
    info: EventSourceFuncArg,
    successCallback: (
        events: Array<{
            id: string;
            title: string;
            start: string;
            end?: string;
            url: string;
            backgroundColor: string;
        }>
    ) => void,
    failureCallback: (error: Error) => void
): Promise<void> => {
    isLoading.value = true;
    error.value = null;

    try {
        const params = new URLSearchParams({
            start: info.startStr,
            end: info.endStr,
        });

        const response = await fetch(`/api/events/calendar?${params}`);
        if (!response.ok) {
            throw new Error('Failed to fetch events');
        }

        const data: CalendarEvent[] = await response.json();

        // Cache events for tooltip/detail panel access
        eventsCache.value.clear();
        data.forEach((event) => {
            eventsCache.value.set(event.id, event);
        });

        // Emit events loaded for parent component to handle
        emit('eventsLoaded', data);

        const mappedEvents = data.map((event) => {
            const baseEvent = {
                id: event.id,
                title: event.title,
                start: event.start,
                url: event.url,
                backgroundColor: event.backgroundColor,
            };
            return event.end !== null ? { ...baseEvent, end: event.end } : baseEvent;
        });

        successCallback(mappedEvents);
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

const calendarOptions = computed<CalendarOptions>(() => {
    const baseOptions: CalendarOptions = {
        plugins: [dayGridPlugin, interactionPlugin],
        initialView: 'dayGridMonth',
        locale: locale.value,
        events: fetchEvents,
        eventClick: handleEventClick,
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
        <div v-if="error" role="alert" class="mb-4 rounded-lg bg-red-50 p-4 text-center">
            <p class="text-sm font-medium text-red-800">
                {{ error }}
            </p>
        </div>

        <div class="relative">
            <div
                v-if="isLoading"
                class="absolute inset-0 z-10 flex items-center justify-center bg-white/70"
            >
                <div class="text-center">
                    <div
                        class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-amber-500 border-t-transparent"
                    ></div>
                    <p class="mt-2 text-sm text-gray-600">
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

<style scoped>
.event-calendar :deep(.fc) {
    --fc-border-color: #e5e7eb;
    --fc-button-bg-color: #f59e0b;
    --fc-button-border-color: #f59e0b;
    --fc-button-hover-bg-color: #d97706;
    --fc-button-hover-border-color: #d97706;
    --fc-button-active-bg-color: #b45309;
    --fc-button-active-border-color: #b45309;
    --fc-event-bg-color: #f59e0b;
    --fc-event-border-color: #f59e0b;
    --fc-today-bg-color: #fef3c7;
}

.event-calendar :deep(.fc-theme-standard th) {
    background-color: #fffbeb;
    border-color: var(--fc-border-color);
}

.event-calendar :deep(.fc-theme-standard td) {
    border-color: var(--fc-border-color);
}

.event-calendar :deep(.fc-toolbar-title) {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
}

.event-calendar :deep(.fc-button) {
    text-transform: capitalize;
    font-weight: 500;
}

.event-calendar :deep(.fc-event) {
    cursor: pointer;
    transition: transform 0.15s ease-in-out;
}

.event-calendar :deep(.fc-event:hover) {
    transform: scale(1.05);
}

.event-calendar :deep(.fc-daygrid-event) {
    white-space: normal;
    align-items: flex-start;
}

.event-calendar :deep(.fc-event-title) {
    font-weight: 500;
}

/* ===== COMPACT MODE REDESIGN ===== */

/* Remove all borders */
.event-calendar--compact :deep(.fc-theme-standard th),
.event-calendar--compact :deep(.fc-theme-standard td),
.event-calendar--compact :deep(.fc-theme-standard .fc-scrollgrid),
.event-calendar--compact :deep(.fc-scrollgrid-sync-table) {
    border: none !important;
}

/* Title styling */
.event-calendar--compact :deep(.fc-toolbar-title) {
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
}

/* Navigation buttons */
.event-calendar--compact :deep(.fc-button) {
    background: none !important;
    border: none !important;
    color: #6b7280;
    padding: 0.25rem 0.5rem;
    box-shadow: none !important;
}

.event-calendar--compact :deep(.fc-button:hover) {
    color: #f59e0b;
    background: none !important;
}

.event-calendar--compact :deep(.fc-button:focus) {
    box-shadow: none !important;
}

/* Day header (L M X J V S D) */
.event-calendar--compact :deep(.fc-col-header-cell) {
    background: transparent;
    padding: 0.5rem 0;
}

.event-calendar--compact :deep(.fc-col-header-cell-cushion) {
    font-size: 0.75rem;
    font-weight: 500;
    color: #9ca3af;
    text-transform: uppercase;
}

/* Day cells */
.event-calendar--compact :deep(.fc-daygrid-day) {
    cursor: pointer;
}

.event-calendar--compact :deep(.fc-daygrid-day-frame) {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    min-height: 40px;
    padding: 2px 0;
}

/* Day numbers */
.event-calendar--compact :deep(.fc-daygrid-day-top) {
    flex-direction: row;
    justify-content: center;
}

.event-calendar--compact :deep(.fc-daygrid-day-number) {
    font-size: 0.875rem;
    padding: 0;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background-color 0.15s ease;
}

/* Hover effect on day numbers */
.event-calendar--compact :deep(.fc-daygrid-day:not(.fc-day-today):hover .fc-daygrid-day-number) {
    background-color: #f3f4f6;
}

/* Today highlight */
.event-calendar--compact :deep(.fc-day-today) {
    background: transparent !important;
}

.event-calendar--compact :deep(.fc-day-today .fc-daygrid-day-number) {
    background-color: #f59e0b;
    color: white;
    font-weight: 600;
}

/* Days outside current month */
.event-calendar--compact :deep(.fc-day-other .fc-daygrid-day-number) {
    color: #9ca3af;
}

/* Event dots on days outside current month - more subtle */
.event-calendar--compact :deep(.fc-day-other .fc-daygrid-event-dot) {
    opacity: 0.35;
}

/* Events container - position for dots */
.event-calendar--compact :deep(.fc-daygrid-day-events) {
    display: flex;
    justify-content: center;
    gap: 2px;
    min-height: 8px;
    margin-top: 2px;
}

.event-calendar--compact :deep(.fc-daygrid-day-bottom) {
    display: none;
}

/* Event dots */
.event-calendar--compact :deep(.fc-daygrid-event-harness) {
    margin: 0 !important;
}

.event-calendar--compact :deep(.fc-daygrid-event) {
    margin: 0;
    padding: 0;
    background: none !important;
    border: none !important;
}

.event-calendar--compact :deep(.fc-daygrid-event-dot) {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #f59e0b;
    border: none !important;
    margin: 0;
}

/* Hide event title text in compact mode */
.event-calendar--compact :deep(.fc-event-title) {
    display: none;
}

.event-calendar--compact :deep(.fc-event-time) {
    display: none;
}

/* Fix for list-item display mode */
.event-calendar--compact :deep(.fc-daygrid-event-harness-abs) {
    position: relative !important;
}
</style>
