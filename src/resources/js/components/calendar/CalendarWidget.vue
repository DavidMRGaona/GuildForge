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
    <div class="calendar-widget rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <EventCalendar
            compact
            :navigate-on-click="false"
            @event-select="navigateToCalendar"
            @calendar-click="navigateToCalendar"
        />

        <div class="mt-4 border-t border-gray-100 pt-4">
            <button
                type="button"
                class="w-full text-center text-sm font-medium text-amber-600 hover:text-amber-700"
                @click="navigateToCalendar"
            >
                {{ t('calendar.viewFull') }} &rarr;
            </button>
        </div>
    </div>
</template>

<style scoped>
.calendar-widget :deep(.event-calendar) {
    margin: 0;
}
</style>
