<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Gallery, PaginatedResponse } from '@/types';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import GalleryGrid from '@/components/gallery/GalleryGrid.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useSeo } from '@/composables/useSeo';
import { usePagination } from '@/composables/usePagination';

interface Props {
    galleries: PaginatedResponse<Gallery>;
}

const props = defineProps<Props>();

const { t } = useI18n();

useSeo({
    title: t('gallery.title'),
    description: t('gallery.subtitle'),
});

const isNavigating = ref(false);

const { firstItemNumber, lastItemNumber, hasPagination, goToPrev, goToNext, canGoPrev, canGoNext } =
    usePagination(() => props.galleries);

function handlePrev(): void {
    isNavigating.value = true;
    goToPrev();
}

function handleNext(): void {
    isNavigating.value = true;
    goToNext();
}
</script>

<template>
    <DefaultLayout>
        <div class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">
                    {{ t('gallery.title') }}
                </h1>
                <p class="mt-2 text-lg text-gray-600">
                    {{ t('gallery.subtitle') }}
                </p>
            </div>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <GalleryGrid :galleries="props.galleries.data" />

            <div
                v-if="hasPagination"
                class="mt-8 flex items-center justify-between border-t border-gray-200 pt-6"
            >
                <p class="text-sm text-gray-700">
                    {{ t('common.showing') }}
                    <span class="font-medium">
                        {{ firstItemNumber }}
                    </span>
                    -
                    <span class="font-medium">
                        {{ lastItemNumber }}
                    </span>
                    {{ t('common.of') }}
                    <span class="font-medium">{{ props.galleries.meta.total }}</span>
                </p>

                <div class="flex gap-2">
                    <BaseButton
                        variant="secondary"
                        :disabled="!canGoPrev"
                        :loading="isNavigating"
                        @click="handlePrev"
                    >
                        {{ t('common.previous') }}
                    </BaseButton>
                    <BaseButton
                        variant="secondary"
                        :disabled="!canGoNext"
                        :loading="isNavigating"
                        @click="handleNext"
                    >
                        {{ t('common.next') }}
                    </BaseButton>
                </div>
            </div>
        </main>
    </DefaultLayout>
</template>
