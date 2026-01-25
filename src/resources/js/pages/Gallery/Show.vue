<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Gallery, Photo } from '@/types/models';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import TagBadge from '@/components/ui/TagBadge.vue';
import TagList from '@/components/ui/TagList.vue';
import { useLightbox } from '@/composables/useLightbox';
import { useGallery } from '@/composables/useGallery';
import { useTags } from '@/composables/useTags';
import { useSeo } from '@/composables/useSeo';
import { buildGalleryImageUrl } from '@/utils/cloudinary';

const PhotoLightbox = defineAsyncComponent(() => import('@/components/gallery/PhotoLightbox.vue'));

interface Props {
    gallery: Gallery;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { formatGalleryDate } = useGallery();

useSeo({
    title: props.gallery.title,
    description: props.gallery.description ?? t('gallery.subtitle'),
});

const photos = computed<Photo[]>(() => props.gallery.photos ?? []);
const { isOpen, currentIndex, open, close, next, prev } = useLightbox(photos);

const { categoryTag, additionalTags, hasTags } = useTags(computed(() => props.gallery.tags));

function openLightbox(index: number): void {
    open(index);
}

function getPhotoThumbnailUrl(photo: Photo): string | null {
    return buildGalleryImageUrl(photo.imagePublicId);
}
</script>

<template>
    <DefaultLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <Link
                    href="/galeria"
                    class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 rounded dark:text-stone-400 dark:hover:text-stone-300 dark:focus:ring-offset-stone-900"
                >
                    <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                    {{ t('common.back') }}
                </Link>
            </div>

            <div
                class="overflow-hidden rounded-lg bg-white shadow dark:bg-stone-800 dark:shadow-stone-900/50"
            >
                <div
                    class="border-b border-gray-200 bg-gradient-to-r from-amber-500 to-slate-600 px-6 py-8 text-white dark:border-stone-700"
                >
                    <h1 class="text-3xl font-bold sm:text-4xl">
                        {{ props.gallery.title }}
                    </h1>
                    <p v-if="props.gallery.description" class="mt-4 text-lg text-amber-100">
                        {{ props.gallery.description }}
                    </p>

                    <!-- Tags -->
                    <div v-if="hasTags" class="mt-4 flex flex-wrap items-center gap-2">
                        <TagBadge
                            v-if="categoryTag"
                            :tag="categoryTag"
                            variant="category"
                            content-type="galleries"
                        />
                        <TagList
                            v-if="additionalTags.length"
                            :tags="additionalTags"
                            content-type="galleries"
                        />
                    </div>

                    <p class="mt-4 text-sm text-amber-100/80">
                        {{ formatGalleryDate(props.gallery.createdAt) }} · {{ photos.length }}
                        {{ t('gallery.photos') }}
                    </p>
                </div>

                <div class="p-6">
                    <div
                        v-if="photos.length > 0"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4"
                    >
                        <button
                            v-for="(photo, index) in photos"
                            :key="photo.id"
                            type="button"
                            :aria-label="
                                t('a11y.openPhoto', {
                                    number: index + 1,
                                    caption: photo.caption ?? '',
                                })
                            "
                            class="group relative aspect-square overflow-hidden rounded-lg bg-gray-100 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:bg-stone-700 dark:focus:ring-offset-stone-900"
                            @click="openLightbox(index)"
                        >
                            <img
                                v-if="getPhotoThumbnailUrl(photo)"
                                :src="getPhotoThumbnailUrl(photo)!"
                                :alt="photo.caption ?? ''"
                                loading="lazy"
                                class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                            />
                            <div
                                class="absolute inset-0 bg-black/0 transition-colors duration-200 group-hover:bg-black/20"
                            />
                            <div
                                v-if="photo.caption"
                                class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-3 opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                            >
                                <p class="line-clamp-2 text-sm text-white">
                                    {{ photo.caption }}
                                </p>
                            </div>
                        </button>
                    </div>

                    <div v-else class="py-12 text-center">
                        <svg
                            class="mx-auto h-12 w-12 text-gray-400 dark:text-stone-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-stone-100">
                            {{ t('common.noResults') }}
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <PhotoLightbox
            :photos="photos"
            :current-index="currentIndex"
            :is-open="isOpen"
            @close="close"
            @next="next"
            @prev="prev"
        />
    </DefaultLayout>
</template>
