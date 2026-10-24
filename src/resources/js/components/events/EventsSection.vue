<script setup lang="ts">
import { Calendar } from '@lucide/vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Event } from '@/types/models';
import EventCard from './EventCard.vue';
import CalendarWidget from '@/components/calendar/CalendarWidget.vue';
import { useRoutes } from '@/composables/useRoutes';

interface Props {
    events: Event[];
    arePast?: boolean;
    maxEvents?: number;
}

const props = withDefaults(defineProps<Props>(), {
    arePast: false,
    maxEvents: 2,
});

const { t } = useI18n();
const routes = useRoutes();

const displayEvents = computed(() => props.events.slice(0, props.maxEvents));
const hasEvents = computed(() => props.events.length > 0);
const sectionTitle = computed(() =>
    props.arePast ? t('home.recentEvents') : t('home.upcomingEvents')
);
</script>

<template>
    <section class="events-section">
        <!-- Section header -->
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-3xl font-bold tracking-tight text-base-primary">
                {{ sectionTitle }}
            </h2>
            <Link
                v-if="hasEvents"
                :href="routes.events.index"
                class="text-sm font-medium text-primary transition-colors hover:text-primary-700 dark:hover:text-primary-400"
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
                    class="flex flex-1 items-center justify-center rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-8 dark:border-neutral-600 dark:bg-muted"
                >
                    <div class="text-center">
                        <Calendar
                            class="mx-auto h-8 w-8 text-neutral-400 dark:text-neutral-500"
                            aria-hidden="true"
                        />
                        <p class="mt-2 text-sm text-base-muted">
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
