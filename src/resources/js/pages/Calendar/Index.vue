<script setup lang="ts">
import { X } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { CalendarEntry } from '@/types/models';
import type { CalendarSubscriptionLinks } from '@/types/calendarFeed';
import EventCalendar from '@/components/calendar/EventCalendar.vue';
import EventDetailPanel from '@/components/calendar/EventDetailPanel.vue';
import CalendarSubscribeMenu from '@/components/calendar/CalendarSubscribeMenu.vue';
import HelpLink from '@/components/knowledge/HelpLink.vue';
import EventsViewToggle from '@/components/events/EventsViewToggle.vue';
import FormToggle from '@/components/form/FormToggle.vue';
import { extractLegend, type CalendarLegendItem } from '@/utils/calendarEntries';
import { useMediaQuery } from '@/composables/useMediaQuery';

interface Props {
    /** Whether to offer the "only mine" filter (signed in, with some module that tells attendance). */
    personalCalendar?: boolean;
    /** Addresses to subscribe to the public calendar feed, without an account. */
    publicCalendarFeed?: CalendarSubscriptionLinks | null;
}

withDefaults(defineProps<Props>(), {
    personalCalendar: false,
    publicCalendarFeed: null,
});

const { t } = useI18n();
const isMobile = useMediaQuery('(max-width: 1023px)');

const selectedEvent = ref<CalendarEntry | null>(null);
const showMobileModal = ref(false);
const hasSetDefaultEvent = ref(false);
const legend = ref<CalendarLegendItem[]>([]);
const onlyMine = ref(false);
const showOnlyMineEmpty = ref(false);

const handleEventsLoaded = (events: CalendarEntry[]): void => {
    legend.value = extractLegend(events);
    showOnlyMineEmpty.value = onlyMine.value && events.length === 0;

    // Only set default once (first load)
    if (hasSetDefaultEvent.value || events.length === 0) {
        return;
    }

    const now = new Date();

    // Sort events by start date
    const sortedEvents = [...events].sort(
        (a, b) => new Date(a.start).getTime() - new Date(b.start).getTime()
    );

    // Find next upcoming event (date >= today)
    const upcomingEvent = sortedEvents.find((event) => new Date(event.start) >= now);

    if (upcomingEvent) {
        selectedEvent.value = upcomingEvent;
    } else {
        // If no upcoming events, show the most recent (last in sorted list)
        const mostRecentEvent = sortedEvents[sortedEvents.length - 1];
        if (mostRecentEvent) {
            selectedEvent.value = mostRecentEvent;
        }
    }

    hasSetDefaultEvent.value = true;
};

// The other entries are gone: pick the next one of the new set as on the first load
watch(onlyMine, () => {
    selectedEvent.value = null;
    hasSetDefaultEvent.value = false;
});

const handleEventSelect = (event: CalendarEntry): void => {
    selectedEvent.value = event;

    // On mobile, show the modal
    if (isMobile.value) {
        showMobileModal.value = true;
    }
};

const closeMobileModal = (): void => {
    showMobileModal.value = false;
};
</script>

<template>
    <!--
        Title band, as in the other lists. The list/calendar toggle sits at the top right, where
        the event list has it, so it does not move between the two views; on desktop the filter
        goes below it, over the detail panel's column.
    -->
    <div data-testid="page-band" class="page-band">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-3 lg:gap-x-8">
                <div class="flex items-start justify-between gap-4 lg:col-span-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3">
                            <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                                {{ t('calendar.title') }}
                            </h1>
                            <HelpLink guide="core.events.find-events" />
                        </div>
                        <p class="mt-2 text-lg text-base-secondary">
                            {{ t('calendar.subtitle') }}
                        </p>
                    </div>
                    <div class="lg:hidden">
                        <EventsViewToggle current="calendar" />
                    </div>
                </div>

                <div class="lg:flex lg:flex-col lg:items-end">
                    <div class="hidden lg:block">
                        <EventsViewToggle current="calendar" />
                    </div>

                    <div
                        v-if="personalCalendar"
                        class="mt-4 max-w-sm lg:mt-3 lg:max-w-none lg:self-stretch"
                    >
                        <div class="flex min-h-9 items-center gap-2">
                            <FormToggle
                                id="calendar-only-mine"
                                v-model="onlyMine"
                                :label="t('calendar.onlyMine')"
                            />
                            <HelpLink guide="core.events.personal-calendar" />
                        </div>
                        <p class="mt-1 text-xs text-base-muted">
                            {{ t('calendar.onlyMineHint') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <p
            v-if="showOnlyMineEmpty"
            role="status"
            class="mb-6 rounded-lg bg-muted p-4 text-sm text-base-secondary"
        >
            {{ t('calendar.onlyMineEmpty') }}
        </p>

        <div class="lg:grid lg:grid-cols-3 lg:gap-x-8">
            <!-- Calendar (2/3 width on desktop) -->
            <div class="lg:col-span-2">
                <div class="hidden lg:block">
                    <EventCalendar
                        show-tooltips
                        :only-mine="onlyMine"
                        :navigate-on-click="false"
                        @event-select="handleEventSelect"
                        @events-loaded="handleEventsLoaded"
                    />
                </div>
                <div class="lg:hidden">
                    <EventCalendar
                        :only-mine="onlyMine"
                        :navigate-on-click="false"
                        @event-select="handleEventSelect"
                        @events-loaded="handleEventsLoaded"
                    />
                </div>

                <!--
                    Legend and the public subscription, below the calendar. On phones the
                    button takes its own row.
                -->
                <div
                    v-if="legend.length > 0 || publicCalendarFeed"
                    class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-3"
                >
                    <ul
                        v-if="legend.length > 0"
                        class="flex list-none flex-wrap items-center gap-x-4 gap-y-2 p-0"
                        :aria-label="t('calendar.legendLabel')"
                    >
                        <li
                            v-for="item in legend"
                            :key="item.sourceType"
                            class="flex items-center gap-1.5 text-sm text-base-secondary"
                        >
                            <span
                                :class="[
                                    'calendar-legend-dot',
                                    `calendar-legend-dot--${item.color}`,
                                ]"
                                aria-hidden="true"
                            ></span>
                            {{ item.sourceLabel }}
                        </li>
                    </ul>
                    <div v-if="publicCalendarFeed" class="basis-full sm:ml-auto sm:basis-auto">
                        <CalendarSubscribeMenu
                            :links="publicCalendarFeed"
                            align="end"
                            placement="top"
                        />
                    </div>
                </div>
            </div>

            <!--
                Detail panel (1/3 width, desktop only; mobile opens it in a modal). It starts
                level with the day grid, not the toolbar: mt-14 is the toolbar band (36 px
                buttons + the theme's 20 px gap), checked in the browser at lg and xl.
            -->
            <div class="hidden lg:col-start-3 lg:mt-14 lg:block">
                <div class="sticky top-4">
                    <EventDetailPanel :event="selectedEvent" />
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-300"
            leave-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showMobileModal"
                class="fixed inset-0 z-50 lg:hidden"
                @click="closeMobileModal"
                @keydown.esc="closeMobileModal"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50"></div>

                <!-- Modal content -->
                <Transition
                    enter-active-class="transition-transform duration-300"
                    leave-active-class="transition-transform duration-300"
                    enter-from-class="translate-y-full"
                    leave-to-class="translate-y-full"
                >
                    <div
                        v-if="showMobileModal"
                        class="absolute inset-x-0 bottom-0 max-h-[80vh] overflow-y-auto rounded-t-xl bg-surface p-4 shadow-xl"
                        @click.stop
                    >
                        <!-- Close button -->
                        <div class="mb-4 flex justify-end">
                            <button
                                type="button"
                                class="rounded-full p-2 text-base-muted hover:bg-muted hover:text-base-secondary"
                                @click="closeMobileModal"
                            >
                                <span class="sr-only">{{ t('buttons.close') }}</span>
                                <X class="h-5 w-5" aria-hidden="true" />
                            </button>
                        </div>

                        <!-- Event details -->
                        <EventDetailPanel :event="selectedEvent" />
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
