<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { CalendarEvent } from '@/types/models';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import EventCalendar from '@/components/calendar/EventCalendar.vue';
import EventDetailPanel from '@/components/calendar/EventDetailPanel.vue';
import { useSeo } from '@/composables/useSeo';
import { useMediaQuery } from '@/composables/useMediaQuery';

const { t } = useI18n();
const isMobile = useMediaQuery('(max-width: 1023px)');

useSeo({
    title: t('calendar.title'),
    description: t('calendar.title'),
});

const selectedEvent = ref<CalendarEvent | null>(null);
const showMobileModal = ref(false);
const hasSetDefaultEvent = ref(false);

const handleEventsLoaded = (events: CalendarEvent[]): void => {
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

const handleEventSelect = (event: CalendarEvent): void => {
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
    <DefaultLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Page header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
                    {{ t('calendar.title') }}
                </h1>
            </div>

            <!-- Desktop layout: Calendar + Side panel -->
            <div class="hidden lg:grid lg:grid-cols-3 lg:gap-8">
                <!-- Calendar (2/3 width) -->
                <div class="lg:col-span-2">
                    <EventCalendar
                        show-tooltips
                        :navigate-on-click="false"
                        @event-select="handleEventSelect"
                        @events-loaded="handleEventsLoaded"
                    />
                </div>

                <!-- Detail panel (1/3 width) -->
                <div class="lg:col-span-1">
                    <div class="sticky top-4">
                        <EventDetailPanel :event="selectedEvent" />
                    </div>
                </div>
            </div>

            <!-- Mobile layout: Calendar only -->
            <div class="lg:hidden">
                <EventCalendar
                    :navigate-on-click="false"
                    @event-select="handleEventSelect"
                    @events-loaded="handleEventsLoaded"
                />
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
                            class="absolute inset-x-0 bottom-0 max-h-[80vh] overflow-y-auto rounded-t-xl bg-white dark:bg-stone-800 p-4 shadow-xl"
                            @click.stop
                        >
                            <!-- Close button -->
                            <div class="mb-4 flex justify-end">
                                <button
                                    type="button"
                                    class="rounded-full p-2 text-stone-400 hover:bg-stone-100 dark:hover:bg-stone-700 hover:text-stone-500 dark:hover:text-stone-300"
                                    @click="closeMobileModal"
                                >
                                    <span class="sr-only">{{ t('buttons.close') }}</span>
                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <!-- Event details -->
                            <EventDetailPanel :event="selectedEvent" />
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </DefaultLayout>
</template>
