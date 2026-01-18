<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { Gallery } from '@/types/models';
import GalleryCard from './GalleryCard.vue';
import { useGridLayout, type GridColumns } from '@/composables/useGridLayout';

interface Props {
    galleries: Gallery[];
    columns?: GridColumns;
}

const props = withDefaults(defineProps<Props>(), {
    columns: 3,
});

const { t } = useI18n();

const { gridClasses } = useGridLayout(() => props.columns);
</script>

<template>
    <div v-if="props.galleries.length > 0" class="grid gap-6" :class="gridClasses">
        <GalleryCard v-for="gallery in props.galleries" :key="gallery.id" :gallery="gallery" />
    </div>

    <div v-else class="rounded-lg bg-white p-12 text-center shadow">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">{{ t('common.noResults') }}</h3>
        <p class="mt-2 text-sm text-gray-500">{{ t('gallery.noGalleries') }}</p>
    </div>
</template>
