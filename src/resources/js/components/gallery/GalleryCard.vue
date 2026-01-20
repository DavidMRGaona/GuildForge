<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Gallery } from '@/types/models';
import { useGallery } from '@/composables/useGallery';
import { useTags } from '@/composables/useTags';
import BaseCard from '@/components/ui/BaseCard.vue';
import ImagePlaceholder from '@/components/ui/ImagePlaceholder.vue';
import TagBadge from '@/components/ui/TagBadge.vue';
import TagList from '@/components/ui/TagList.vue';
import { buildCardImageUrl } from '@/utils/cloudinary';

interface Props {
    gallery: Gallery;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { getPhotoCount, getGalleryExcerpt } = useGallery();

const coverImageUrl = computed(() => buildCardImageUrl(props.gallery.coverImagePublicId));

const { categoryTag, additionalTags } = useTags(computed(() => props.gallery.tags));
</script>

<template>
    <Link
        :href="`/galeria/${props.gallery.slug}`"
        :aria-label="t('a11y.viewGallery', { title: props.gallery.title })"
        class="block transition-transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
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
                    <ImagePlaceholder v-else variant="gallery" height="h-48" icon-size="h-16 w-16" />

                    <!-- Category badge as overlay on image -->
                    <TagBadge
                        v-if="categoryTag"
                        :tag="categoryTag"
                        :linkable="false"
                        variant="category"
                        content-type="galleries"
                        class="absolute left-3 top-3"
                    />

                    <div class="absolute bottom-2 right-2 rounded-full bg-slate-600 px-3 py-1 text-xs font-medium text-white">
                        {{ getPhotoCount(props.gallery) }}
                    </div>
                </div>
            </template>

            <div class="p-4">
                <h3 class="mb-2 line-clamp-2 text-lg font-semibold text-gray-900">
                    {{ props.gallery.title }}
                </h3>

                <!-- Additional tags below title -->
                <TagList
                    v-if="additionalTags.length"
                    :tags="additionalTags"
                    :linkable="false"
                    content-type="galleries"
                    class="mb-2"
                />

                <p v-if="props.gallery.description" class="line-clamp-2 text-sm text-gray-600">
                    {{ getGalleryExcerpt(props.gallery.description) }}
                </p>
            </div>
        </BaseCard>
    </Link>
</template>
