<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { Event } from '@/types/models';
import EventCard from './EventCard.vue';
import { useGridLayout, type GridColumns } from '@/composables/useGridLayout';

interface Props {
    events: Event[];
    columns?: GridColumns;
}

const props = withDefaults(defineProps<Props>(), {
    columns: 3,
});

const { t } = useI18n();

const { gridClasses } = useGridLayout(() => props.columns);
</script>

<template>
    <div v-if="props.events.length > 0" class="grid gap-6" :class="gridClasses">
        <EventCard v-for="event in props.events" :key="event.id" :event="event" />
    </div>

    <div v-else class="rounded-lg bg-white p-12 text-center shadow">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">{{ t('common.noResults') }}</h3>
        <p class="mt-2 text-sm text-gray-500">{{ t('events.noUpcoming') }}</p>
    </div>
</template>
