<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Gallery } from '@/types/models';
import { useGallery } from '@/composables/useGallery';
import BaseCard from '@/components/ui/BaseCard.vue';
import { buildCardImageUrl } from '@/utils/cloudinary';

interface Props {
    gallery: Gallery;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { getPhotoCount, getGalleryExcerpt } = useGallery();

const coverImageUrl = computed(() => buildCardImageUrl(props.gallery.coverImagePublicId));
</script>

<template>
    <Link
        :href="`/galeria/${props.gallery.slug}`"
        :aria-label="t('a11y.viewGallery', { title: props.gallery.title })"
        class="block transition-transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
    >
        <BaseCard :padding="false">
            <template #header>
                <div class="relative">
                    <img
                        v-if="coverImageUrl"
                        :src="coverImageUrl"
                        :alt="props.gallery.title"
                        loading="lazy"
                        class="aspect-video h-48 w-full object-cover"
                    />
                    <div
                        v-else
                        class="flex aspect-video h-48 w-full items-center justify-center bg-gradient-to-br from-purple-400 to-purple-600"
                    >
                        <svg
                            class="h-16 w-16 text-white/50"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                    </div>
                    <div class="absolute bottom-2 right-2 rounded-full bg-purple-600 px-3 py-1 text-xs font-medium text-white">
                        {{ getPhotoCount(props.gallery) }}
                    </div>
                </div>
            </template>

            <div class="p-4">
                <h3 class="mb-2 line-clamp-2 text-lg font-semibold text-gray-900">
                    {{ props.gallery.title }}
                </h3>

                <p v-if="props.gallery.description" class="line-clamp-2 text-sm text-gray-600">
                    {{ getGalleryExcerpt(props.gallery.description) }}
                </p>
            </div>
        </BaseCard>
    </Link>
</template>
