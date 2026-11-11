<script setup lang="ts">
import { Calendar, List } from '@lucide/vue';
// Switches between the event list and the calendar, on every screen size: two links in the
// title band, the current one marked with aria-current (as the tables' list/calendar toggle)
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useRoutes } from '@/composables/useRoutes';

interface Props {
    current: 'list' | 'calendar';
}

defineProps<Props>();

const { t } = useI18n();
const routes = useRoutes();

const baseClass =
    'inline-flex min-h-[44px] min-w-[44px] items-center justify-center border px-3 py-2 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-surface';
const activeClass = 'border-primary-600 bg-primary text-white';
const inactiveClass =
    'border-default bg-transparent text-base-secondary hover:bg-base-secondary/10';
</script>

<template>
    <nav :aria-label="t('events.views.switch')" class="flex shrink-0 items-center">
        <Link
            :href="routes.events.index"
            :aria-current="current === 'list' ? 'page' : undefined"
            :aria-label="t('events.views.list')"
            :class="[baseClass, 'rounded-l-lg', current === 'list' ? activeClass : inactiveClass]"
        >
            <List class="h-5 w-5" aria-hidden="true" />
        </Link>
        <Link
            :href="routes.calendar"
            :aria-current="current === 'calendar' ? 'page' : undefined"
            :aria-label="t('events.views.calendar')"
            :class="[
                baseClass,
                'rounded-r-lg',
                current === 'calendar' ? activeClass : inactiveClass,
            ]"
        >
            <Calendar class="h-5 w-5" aria-hidden="true" />
        </Link>
    </nav>
</template>
