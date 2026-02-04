<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Tag } from '@/types/models';
import { hexToRgb, adjustColorBrightness } from '@/utils/color';
import { useAppStore } from '@/stores/useAppStore';

interface Props {
    tags: Tag[];
    currentTags: string[];
    basePath: string;
}

const props = withDefaults(defineProps<Props>(), {
    currentTags: () => [],
});

const { t } = useI18n();
const appStore = useAppStore();

/**
 * Get subtle style for a tag.
 * In dark mode: higher opacity background, much lighter text
 * In light mode: lower opacity background, darker text
 */
function getSubtleStyles(hex: string): { backgroundColor: string; color: string } {
    const rgb = hexToRgb(hex);
    const isDark = appStore.isDarkMode;
    const textColor = isDark ? adjustColorBrightness(hex, 80) : adjustColorBrightness(hex, -35);
    const bgOpacity = isDark ? 0.4 : 0.2;
    return {
        backgroundColor: `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${bgOpacity})`,
        color: textColor,
    };
}

/**
 * Build URL with current tags as CSV query param.
 */
function buildTagsUrl(tagSlugs: string[]): string {
    const url = new globalThis.URL(props.basePath, window.location.origin);
    const currentParams = new URLSearchParams(window.location.search);

    // Preserve existing params except tags and page
    currentParams.forEach((value, key) => {
        if (key !== 'tags' && key !== 'page') {
            url.searchParams.set(key, value);
        }
    });

    // Add tags if any are selected
    if (tagSlugs.length > 0) {
        url.searchParams.set('tags', tagSlugs.join(','));
    }

    return url.pathname + (url.search ? url.search : '');
}

/**
 * Toggle a tag in the current selection.
 */
function toggleTag(tagSlug: string): void {
    const newTags = isActive(tagSlug)
        ? props.currentTags.filter((slug) => slug !== tagSlug)
        : [...props.currentTags, tagSlug];

    router.visit(buildTagsUrl(newTags), {
        preserveState: true,
        preserveScroll: true,
    });
}

/**
 * Clear all selected tags.
 */
function clearTags(): void {
    router.visit(buildTagsUrl([]), {
        preserveState: true,
        preserveScroll: true,
    });
}

function isActive(tagSlug: string): boolean {
    return props.currentTags.includes(tagSlug);
}

const hasActiveTags = computed(() => props.currentTags.length > 0);
</script>

<template>
    <div v-if="tags.length > 0" class="flex flex-wrap items-center gap-2">
        <span class="text-sm font-medium text-base-secondary"> {{ t('tags.filter') }}: </span>

        <button
            type="button"
            :class="[
                'rounded-full px-3 py-1 text-sm font-medium transition-colors',
                !hasActiveTags
                    ? 'bg-primary-light text-primary font-semibold'
                    : 'bg-muted text-base-secondary hover:bg-neutral-200 dark:hover:bg-neutral-700',
            ]"
            @click="clearTags"
        >
            {{ t('tags.all') }}
        </button>

        <button
            v-for="tag in tags"
            :key="tag.id"
            type="button"
            :class="[
                'inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-medium transition-colors',
                isActive(tag.slug) ? 'ring-2 ring-offset-1' : 'hover:opacity-80',
            ]"
            :style="{
                ...getSubtleStyles(tag.color),
                '--tw-ring-color': tag.color,
            }"
            @click="toggleTag(tag.slug)"
        >
            <svg
                v-if="isActive(tag.slug)"
                class="h-3.5 w-3.5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="3"
                    d="M5 13l4 4L19 7"
                />
            </svg>
            {{ tag.name }}
        </button>
    </div>
</template>
