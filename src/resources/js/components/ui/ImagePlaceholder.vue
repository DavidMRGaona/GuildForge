<script setup lang="ts">
type PlaceholderVariant = 'event' | 'article' | 'gallery';

interface Props {
    variant: PlaceholderVariant;
    class?: string;
    height?: string;
    iconSize?: string;
}

withDefaults(defineProps<Props>(), {
    class: '',
    height: 'h-48',
    iconSize: 'h-16 w-16',
});

const iconPaths: Record<PlaceholderVariant, string> = {
    event: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    article:
        'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    gallery:
        'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
};
</script>

<template>
    <div
        :class="[
            'flex aspect-video w-full items-center justify-center bg-gradient-to-br from-amber-500 to-stone-600 dark:from-amber-600 dark:to-stone-700',
            height,
            $props.class,
        ]"
    >
        <svg
            :class="['text-white/50', iconSize]"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                :d="iconPaths[variant]"
            />
        </svg>
    </div>
</template>
