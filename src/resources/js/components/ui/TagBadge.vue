<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { Tag } from '@/types/models';
import { getLuminance, hexToRgb, adjustColorBrightness } from '@/utils/color';
import { useAppStore } from '@/stores/useAppStore';

interface Props {
    tag: Tag;
    linkable?: boolean;
    size?: 'sm' | 'md';
    badgeStyle?: 'solid' | 'subtle' | 'overlay';
    variant?: 'category' | 'tag';
    contentType?: 'events' | 'articles' | 'galleries';
}

const props = withDefaults(defineProps<Props>(), {
    linkable: true,
    size: 'sm',
    badgeStyle: 'subtle',
    variant: 'tag',
    contentType: 'events',
});

const appStore = useAppStore();

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
 * Only used for solid style.
 */
const solidTextColor = computed(() => {
    const luminance = getLuminance(props.tag.color);
    return luminance > 0.179 ? 'text-stone-900' : 'text-white';
});

/**
 * Get styles based on the style prop.
 */
const badgeStyles = computed(() => {
    if (props.badgeStyle === 'subtle') {
        const rgb = hexToRgb(props.tag.color);
        // In dark mode: higher opacity background, much lighter text
        // In light mode: lower opacity background, darker text
        const isDark = appStore.isDarkMode;
        const textColor = isDark
            ? adjustColorBrightness(props.tag.color, 80)
            : adjustColorBrightness(props.tag.color, -35);
        const bgOpacity = isDark ? 0.4 : 0.2;
        return {
            backgroundColor: `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${bgOpacity})`,
            color: textColor,
        };
    }
    if (props.badgeStyle === 'overlay') {
        // Overlay badges on images: solid semi-opaque background with high contrast text
        // Works on any colored background (images, cards)
        const isDark = appStore.isDarkMode;
        return {
            backgroundColor: isDark ? 'rgba(28, 25, 23, 0.85)' : 'rgba(255, 255, 255, 0.92)',
            color: isDark ? '#F5F5F4' : '#1C1917',
            backdropFilter: 'blur(8px)',
        };
    }
    // Solid style
    return {
        backgroundColor: props.tag.color,
    };
});

const baseClasses = computed(() => [
    'inline-flex items-center rounded-full transition-all',
    sizeClasses[props.size][props.variant],
    props.badgeStyle === 'solid' ? solidTextColor.value : '',
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
        :style="badgeStyles"
        class="hover:opacity-80"
    >
        {{ tag.name }}
    </Link>
    <span v-else :class="baseClasses" :style="badgeStyles">
        {{ tag.name }}
    </span>
</template>
