<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { Tag } from '@/types/models';

interface Props {
    tag: Tag;
    linkable?: boolean;
    size?: 'sm' | 'md';
    variant?: 'category' | 'tag';
    contentType?: 'events' | 'articles' | 'galleries';
}

const props = withDefaults(defineProps<Props>(), {
    linkable: true,
    size: 'sm',
    variant: 'tag',
    contentType: 'events',
});

const sizeClasses: Record<NonNullable<typeof props.size>, Record<NonNullable<typeof props.variant>, string>> = {
    sm: {
        tag: 'px-2 py-0.5 text-xs',
        category: 'px-2.5 py-1 text-xs font-semibold',
    },
    md: {
        tag: 'px-3 py-1 text-sm',
        category: 'px-3.5 py-1.5 text-sm font-semibold',
    },
};

/**
 * Calculate relative luminance of a color.
 * Used to determine if text should be dark or light.
 */
function getLuminance(hex: string): number {
    const matches = hex.replace('#', '').match(/.{2}/g);
    if (!matches || matches.length < 3) return 0;

    const rgb = matches.map((c) => {
        const value = parseInt(c, 16) / 255;
        return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
    }) as [number, number, number];

    return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
}

/**
 * Determine if text should be dark or light based on background color.
 */
const textColor = computed(() => {
    const luminance = getLuminance(props.tag.color);
    return luminance > 0.179 ? 'text-gray-900' : 'text-white';
});

const baseClasses = computed(() => [
    'inline-flex items-center rounded-full transition-opacity',
    sizeClasses[props.size][props.variant],
    textColor.value,
]);

const urlMap: Record<string, string> = {
    events: '/eventos',
    articles: '/articulos',
    galleries: '/galeria',
};

const tagUrl = computed(() => {
    const basePath = urlMap[props.contentType];
    return `${basePath}?tag=${props.tag.slug}`;
});
</script>

<template>
    <Link
        v-if="linkable"
        :href="tagUrl"
        :class="baseClasses"
        :style="{ backgroundColor: tag.color }"
        class="hover:opacity-80"
    >
        {{ tag.name }}
    </Link>
    <span
        v-else
        :class="baseClasses"
        :style="{ backgroundColor: tag.color }"
    >
        {{ tag.name }}
    </span>
</template>
