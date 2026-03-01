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

        const response = await fetch(`/eventos/calendario?${params}`);
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
