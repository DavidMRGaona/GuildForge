<script setup lang="ts">
import { computed } from 'vue';
import TagBadge from './TagBadge.vue';
import type { Tag } from '@/types/models';

interface Props {
    tags: Tag[];
    maxVisible?: number;
    linkable?: boolean;
    size?: 'sm' | 'md';
    variant?: 'category' | 'tag';
    contentType?: 'events' | 'articles' | 'galleries';
}

const props = withDefaults(defineProps<Props>(), {
    maxVisible: 3,
    linkable: true,
    size: 'sm',
    variant: 'tag',
    contentType: 'events',
});

const visibleTags = computed(() => props.tags.slice(0, props.maxVisible));
const hiddenCount = computed(() => Math.max(0, props.tags.length - props.maxVisible));
</script>

<template>
    <div v-if="tags.length > 0" class="flex flex-wrap items-center gap-1">
        <TagBadge
            v-for="tag in visibleTags"
            :key="tag.id"
            :tag="tag"
            :linkable="linkable"
            :size="size"
            :variant="variant"
            :content-type="contentType"
        />
        <span v-if="hiddenCount > 0" class="text-xs text-stone-500 dark:text-stone-400">
            +{{ hiddenCount }}
        </span>
    </div>
</template>
