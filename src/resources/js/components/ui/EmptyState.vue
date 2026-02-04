<script setup lang="ts">
// 1. Imports
import { computed } from 'vue';

// 2. Props typing
type IconType = 'book' | 'calendar' | 'document' | 'photo' | 'trophy';

interface Props {
    icon: IconType;
    title: string;
    description?: string;
}

const props = defineProps<Props>();

// 3. Computed
const iconPath = computed((): string => {
    const paths: Record<IconType, string> = {
        book: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
        calendar:
            'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        document:
            'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
        photo: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
        trophy: 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m3.044-.001a6.726 6.726 0 002.749-1.35m0 0a6.772 6.772 0 01-2.697 3.423m0 0a6.913 6.913 0 01-5.043.001M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0116.27 9.728m2.48-5.492V2.721',
    };

    return paths[props.icon];
});
</script>

<template>
    <div
        class="rounded-lg bg-surface p-12 text-center shadow dark:shadow-neutral-900/50"
    >
        <svg
            class="mx-auto h-12 w-12 text-neutral-400 dark:text-neutral-500"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="iconPath" />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-base-primary">
            {{ props.title }}
        </h3>
        <p v-if="props.description" class="mt-2 text-sm text-base-muted">
            {{ props.description }}
        </p>
        <div v-if="$slots.default" class="mt-6">
            <slot />
        </div>
    </div>
</template>
