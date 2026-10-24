<script setup lang="ts">
import { Calendar, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import type { CalendarEntry } from '@/types/models';
import { useEvents } from '@/composables/useEvents';

interface Props {
    event: CalendarEntry | null;
    x: number;
    y: number;
    visible: boolean;
}

const props = defineProps<Props>();

const { formatDateRange } = useEvents();

const formattedDate = computed(() => {
    if (!props.event) return '';
    return formatDateRange(props.event.start, props.event.end ?? props.event.start);
});

const tooltipStyle = computed(() => ({
    left: `${props.x + 10}px`,
    top: `${props.y + 10}px`,
}));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150"
            leave-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="visible && event"
                class="pointer-events-none fixed z-50 max-w-xs rounded-lg bg-tooltip px-3.5 py-2.5 text-sm text-tooltip shadow-xl ring-1 ring-tooltip"
                :style="tooltipStyle"
            >
                <!-- Title -->
                <p class="font-semibold">{{ event.title }}</p>

                <!-- Date -->
                <p class="mt-1 text-tooltip-secondary">
                    <Calendar class="mr-1 inline-block h-3 w-3" aria-hidden="true" />
                    {{ formattedDate }}
                </p>

                <!-- Location -->
                <p v-if="event.location" class="mt-1 text-tooltip-secondary">
                    <MapPin class="mr-1 inline-block h-3 w-3" aria-hidden="true" />
                    {{ event.location }}
                </p>

                <!-- Tooltip arrow -->
                <div
                    class="absolute -left-1 top-3 h-2 w-2 rotate-45 bg-tooltip ring-1 ring-tooltip"
                ></div>
            </div>
        </Transition>
    </Teleport>
</template>
