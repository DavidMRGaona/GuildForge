<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { Event } from '@/types/models';
import EventCard from './EventCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
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

    <EmptyState
        v-else
        icon="calendar"
        :title="t('common.noResults')"
        :description="t('events.noUpcoming')"
    />
</template>
