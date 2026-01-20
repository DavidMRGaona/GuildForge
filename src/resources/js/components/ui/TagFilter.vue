<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Tag } from '@/types/models';

interface Props {
    tags: Tag[];
    currentTags: string[];
    basePath: string;
}

const props = withDefaults(defineProps<Props>(), {
    currentTags: () => [],
});

const { t } = useI18n();

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
function getTextColor(hex: string): string {
    const luminance = getLuminance(hex);
    return luminance > 0.179 ? '#111827' : '#ffffff';
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
    <div
        v-if="tags.length > 0"
        class="flex flex-wrap items-center gap-2"
    >
        <span class="text-sm font-medium text-gray-700">
            {{ t('tags.filter') }}:
        </span>

        <button
            type="button"
            :class="[
                'rounded-full px-3 py-1 text-sm font-medium transition-colors',
                !hasActiveTags
                    ? 'bg-amber-600 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
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
                isActive(tag.slug)
                    ? 'ring-2 ring-offset-1'
                    : 'hover:opacity-80',
            ]"
            :style="{
                backgroundColor: tag.color,
                color: getTextColor(tag.color),
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
