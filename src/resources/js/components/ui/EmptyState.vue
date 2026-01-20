<script setup lang="ts">
// 1. Imports
import { computed } from 'vue';

// 2. Props typing
type IconType = 'calendar' | 'document' | 'photo';

interface Props {
    icon: IconType;
    title: string;
    description?: string;
}

const props = defineProps<Props>();

// 3. Computed
const iconPath = computed((): string => {
    const paths: Record<IconType, string> = {
        calendar: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        document: 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
        photo: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
    };

    return paths[props.icon];
});
</script>

<template>
    <div class="rounded-lg bg-white p-12 text-center shadow">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="iconPath" />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">{{ props.title }}</h3>
        <p v-if="props.description" class="mt-2 text-sm text-gray-500">{{ props.description }}</p>
        <div v-if="$slots.default" class="mt-6">
            <slot />
        </div>
    </div>
</template>
