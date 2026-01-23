<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Event } from '@/types/models';
import EventCard from './EventCard.vue';
import CalendarWidget from '@/components/calendar/CalendarWidget.vue';

interface Props {
    events: Event[];
    maxEvents?: number;
}

const props = withDefaults(defineProps<Props>(), {
    maxEvents: 2,
});

const { t } = useI18n();

const displayEvents = computed(() => props.events.slice(0, props.maxEvents));
const hasEvents = computed(() => props.events.length > 0);
</script>

<template>
    <section class="events-section">
        <!-- Section header -->
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-3xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
                {{ t('home.upcomingEvents') }}
            </h2>
            <Link
                v-if="hasEvents"
                href="/eventos"
                class="text-sm font-medium text-amber-600 transition-colors hover:text-amber-700"
            >
                {{ t('common.viewAll') }} &rarr;
            </Link>
        </div>

        <!-- Main grid: Events (2/3) + Calendar (1/3) -->
        <div class="grid grid-cols-1 items-stretch gap-8 lg:grid-cols-3">
            <!-- Left column: Events grid -->
            <div class="flex flex-col lg:col-span-2">
                <div v-if="hasEvents" class="flex flex-1 items-center">
                    <div class="grid w-full grid-cols-1 gap-6 sm:grid-cols-2">
                        <EventCard
                            v-for="event in displayEvents"
                            :key="event.id"
                            :event="event"
                            variant="compact"
                        />
                    </div>
                </div>

                <!-- Empty state (compact) -->
                <div
                    v-else
                    class="flex flex-1 items-center justify-center rounded-lg border border-dashed border-stone-300 bg-stone-50 p-8 dark:border-stone-600 dark:bg-stone-800"
                >
                    <div class="text-center">
                        <svg
                            class="mx-auto h-8 w-8 text-stone-400 dark:text-stone-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                        <p class="mt-2 text-sm text-stone-500 dark:text-stone-400">
                            {{ t('home.noEventsCompact') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right column: Calendar widget -->
            <div class="flex flex-col lg:col-span-1">
                <CalendarWidget class="flex-1" />
            </div>
        </div>
    </section>
</template>
