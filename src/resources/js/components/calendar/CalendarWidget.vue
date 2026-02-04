<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import EventCalendar from './EventCalendar.vue';

const { t } = useI18n();

const navigateToCalendar = (): void => {
    router.visit('/calendario');
};
</script>

<template>
    <div class="calendar-widget flex flex-col justify-center rounded-lg bg-surface p-4">
        <EventCalendar
            compact
            show-tooltips
            :navigate-on-click="false"
            @event-select="navigateToCalendar"
            @calendar-click="navigateToCalendar"
        />

        <div class="mt-3 text-center">
            <button
                type="button"
                class="text-sm font-medium text-primary-600 transition-colors hover:text-primary-700 dark:text-primary-500 dark:hover:text-primary-400"
                @click="navigateToCalendar"
            >
                {{ t('calendar.viewFullCalendar') }} &rarr;
            </button>
        </div>
    </div>
</template>

<style scoped>
.calendar-widget :deep(.event-calendar) {
    margin: 0;
}

.calendar-widget :deep(.fc-toolbar-title) {
    font-family: var(--font-sans), ui-sans-serif, system-ui, sans-serif;
}
</style>
