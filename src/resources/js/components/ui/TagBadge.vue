<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { Tag } from '@/types/models';
import { getLuminance } from '@/utils/color';

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

const sizeClasses: Record<
    NonNullable<typeof props.size>,
    Record<NonNullable<typeof props.variant>, string>
> = {
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
 * Determine if text should be dark or light based on background color.
 */
const textColor = computed(() => {
    const luminance = getLuminance(props.tag.color);
    return luminance > 0.179 ? 'text-stone-900' : 'text-white';
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
    <span v-else :class="baseClasses" :style="{ backgroundColor: tag.color }">
        {{ tag.name }}
    </span>
</template>
