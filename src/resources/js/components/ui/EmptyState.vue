<script setup lang="ts">
import { computed } from 'vue';
import { emptyStateIconMap } from '@/utils/icons';

type IconType = 'book' | 'calendar' | 'document' | 'photo' | 'trophy';

interface Props {
    icon: IconType;
    title: string;
    description?: string;
}

const props = defineProps<Props>();

const iconComponent = computed(() => emptyStateIconMap[props.icon]);
</script>

<template>
    <div class="rounded-lg bg-surface p-12 text-center shadow dark:shadow-neutral-900/50">
        <component
            :is="iconComponent"
            class="mx-auto h-12 w-12 text-neutral-400 dark:text-neutral-500"
            :stroke-width="2"
        />
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
