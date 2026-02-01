<script setup lang="ts">
import { computed } from 'vue';
import type { CalendarEvent } from '@/types/models';
import { useEvents } from '@/composables/useEvents';

interface Props {
    event: CalendarEvent | null;
    x: number;
    y: number;
    visible: boolean;
}

const props = defineProps<Props>();

const { formatDateRange } = useEvents();

const formattedDate = computed(() => {
    if (!props.event) return '';
    return formatDateRange(props.event.start, props.event.end);
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
                class="pointer-events-none fixed z-50 max-w-xs rounded-lg bg-elevated px-3 py-2 text-sm text-white shadow-lg"
                :style="tooltipStyle"
            >
                <!-- Title -->
                <p class="font-semibold">{{ event.title }}</p>

                <!-- Date -->
                <p class="mt-1 text-neutral-300">
                    <svg
                        class="mr-1 inline-block h-3 w-3"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                    {{ formattedDate }}
                </p>

                <!-- Location -->
                <p v-if="event.location" class="mt-1 text-neutral-300">
                    <svg
                        class="mr-1 inline-block h-3 w-3"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                        />
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                        />
                    </svg>
                    {{ event.location }}
                </p>

                <!-- Tooltip arrow -->
                <div class="absolute -left-1 top-3 h-2 w-2 rotate-45 bg-elevated"></div>
            </div>
        </Transition>
    </Teleport>
</template>
