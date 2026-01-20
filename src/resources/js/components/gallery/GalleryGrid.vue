<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { Gallery } from '@/types/models';
import GalleryCard from './GalleryCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
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

    <EmptyState
        v-else
        icon="photo"
        :title="t('common.noResults')"
        :description="t('gallery.noGalleries')"
    />
</template>
