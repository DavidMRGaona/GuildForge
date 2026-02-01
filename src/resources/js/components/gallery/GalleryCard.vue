<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Gallery } from '@/types/models';
import { useGallery } from '@/composables/useGallery';
import { useTags } from '@/composables/useTags';
import { useRoutes } from '@/composables/useRoutes';
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
const routes = useRoutes();

const coverImageUrl = computed(() => buildCardImageUrl(props.gallery.coverImagePublicId));

const { categoryTag, additionalTags } = useTags(computed(() => props.gallery.tags));
</script>

<template>
    <Link
        :href="routes.gallery.show(props.gallery.slug)"
        :aria-label="t('a11y.viewGallery', { title: props.gallery.title })"
        class="group block overflow-hidden rounded-lg bg-surface shadow-sm transition-all duration-200 hover:scale-[1.02] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:shadow-neutral-900/50 dark:focus:ring-offset-page"
    >
        <!-- Image -->
        <div class="relative">
            <img
                v-if="coverImageUrl"
                :src="coverImageUrl"
                :alt="props.gallery.title"
                loading="lazy"
                class="aspect-video h-40 w-full object-cover"
            />
            <ImagePlaceholder
                v-else
                variant="gallery"
                height="h-40"
                icon-size="h-12 w-12"
            />

            <!-- Category badge as overlay on image -->
            <TagBadge
                v-if="categoryTag"
                :tag="categoryTag"
                :linkable="false"
                variant="category"
                badge-style="overlay"
                content-type="galleries"
                class="absolute left-3 top-3"
            />

            <!-- Photo count badge -->
            <div
                class="absolute bottom-2 right-2 rounded-full bg-neutral-600 px-3 py-1 text-xs font-medium text-white dark:bg-neutral-700"
            >
                {{ getPhotoCount(props.gallery) }}
            </div>
        </div>

        <!-- Content -->
        <div class="p-4">
            <h3
                class="mb-2 line-clamp-2 text-lg font-semibold text-base-primary group-hover:text-primary-600 dark:group-hover:text-primary-500"
            >
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

            <p
                v-if="props.gallery.description"
                class="line-clamp-2 text-sm text-base-secondary"
            >
                {{ getGalleryExcerpt(props.gallery.description) }}
            </p>
        </div>
    </Link>
</template>
