<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Event, Article, Gallery, HeroSlide, Photo } from '@/types/models';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import EventsSection from '@/components/events/EventsSection.vue';
import ArticleCard from '@/components/articles/ArticleCard.vue';
import PhotoLightbox from '@/components/gallery/PhotoLightbox.vue';
import HeroSlider from '@/components/hero/HeroSlider.vue';
import { useSeo } from '@/composables/useSeo';
import { useLightbox } from '@/composables/useLightbox';
import { useRoutes } from '@/composables/useRoutes';
import { buildMosaicLargeUrl, buildMosaicSmallUrl } from '@/utils/cloudinary';

interface Props {
    heroSlides: HeroSlide[];
    upcomingEvents: Event[];
    latestArticles: Article[];
    featuredGallery: Gallery | null;
}

const props = defineProps<Props>();

const { t } = useI18n();
const routes = useRoutes();

useSeo({
    description: t('home.subtitle'),
});

// Gallery photos for lightbox
const allPhotos = computed<Photo[]>(() => props.featuredGallery?.photos ?? []);

// Photos to display in the mosaic (max 5 on desktop, 4 on mobile)
const displayPhotos = computed<Photo[]>(() => allPhotos.value.slice(0, 5));

// Remaining photos count for the "+N" overlay
const remainingCount = computed<number>(() => {
    const totalPhotos = props.featuredGallery?.photoCount ?? 0;
    return Math.max(0, totalPhotos - displayPhotos.value.length);
});

// Lightbox state
const lightboxPhotos = ref(allPhotos);
const { isOpen, currentIndex, open, close, next, prev } = useLightbox(lightboxPhotos);

function openLightbox(index: number): void {
    open(index);
}
</script>

<template>
    <DefaultLayout>
        <!-- Hero slider Section -->
        <HeroSlider :slides="props.heroSlides" />

        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <!-- Events Section (Events Grid + Calendar Widget) -->
            <EventsSection :events="props.upcomingEvents" class="mb-16" />

            <!-- Latest Articles Section -->
            <section class="mb-16">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-3xl font-bold tracking-tight text-base-primary">
                        {{ t('articles.latest') }}
                    </h2>
                    <Link
                        v-if="props.latestArticles.length > 0"
                        :href="routes.articles.index"
                        class="text-sm font-medium text-primary hover:opacity-80"
                    >
                        {{ t('common.viewAll') }} &rarr;
                    </Link>
                </div>

                <div
                    v-if="props.latestArticles.length > 0"
                    class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
                >
                    <ArticleCard
                        v-for="article in props.latestArticles.slice(0, 3)"
                        :key="article.id"
                        :article="article"
                    />
                </div>

                <aside
                    v-else
                    role="complementary"
                    class="rounded-lg border-2 border-dashed border-default p-8 text-center"
                >
                    <svg
                        class="mx-auto h-8 w-8 text-base-muted"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        />
                    </svg>
                    <p class="mt-4 text-base-muted">
                        {{ t('home.noArticles') }}
                    </p>
                </aside>
            </section>

            <!-- Featured Gallery Section -->
            <section>
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-3xl font-bold tracking-tight text-base-primary">
                        {{ t('home.featuredGallery') }}
                    </h2>
                    <Link
                        v-if="props.featuredGallery"
                        :href="routes.gallery.show(props.featuredGallery.slug)"
                        class="text-sm font-medium text-primary hover:opacity-80"
                    >
                        {{ t('common.viewAll') }} &rarr;
                    </Link>
                </div>

                <!-- Gallery Mosaic Grid (with photos) -->
                <div v-if="props.featuredGallery && displayPhotos.length > 0">
                    <!-- Asymmetric grid: 4 columns on desktop, 2 on mobile -->
                    <div class="grid grid-cols-2 gap-2 lg:grid-cols-4 lg:grid-rows-2">
                        <!-- Photo 1: Large image spanning 2 columns and 2 rows on desktop -->
                        <button
                            v-if="displayPhotos[0]"
                            type="button"
                            class="group relative aspect-[4/3] overflow-hidden rounded-lg lg:col-span-2 lg:row-span-2 lg:aspect-auto"
                            :aria-label="displayPhotos[0].caption ?? t('gallery.viewPhoto')"
                            @click="openLightbox(0)"
                        >
                            <img
                                :src="buildMosaicLargeUrl(displayPhotos[0].imagePublicId) ?? ''"
                                :alt="displayPhotos[0].caption ?? ''"
                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                            />
                            <div
                                class="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/20"
                            />
                        </button>

                        <!-- Photos 2-5: Small images on the right -->
                        <button
                            v-for="(photo, idx) in displayPhotos.slice(1, 5)"
                            :key="photo.id"
                            type="button"
                            class="group relative aspect-[4/3] overflow-hidden rounded-lg"
                            :aria-label="photo.caption ?? t('gallery.viewPhoto')"
                            @click="openLightbox(idx + 1)"
                        >
                            <img
                                :src="buildMosaicSmallUrl(photo.imagePublicId) ?? ''"
                                :alt="photo.caption ?? ''"
                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                            />
                            <div
                                class="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/20"
                            />

                            <!-- "+N photos" overlay on the last visible cell -->
                            <div
                                v-if="
                                    remainingCount > 0 &&
                                    idx === Math.min(3, displayPhotos.length - 2)
                                "
                                class="absolute inset-0 flex items-center justify-center bg-black/50"
                            >
                                <span class="text-2xl font-bold text-white"
                                    >+{{ remainingCount }}</span
                                >
                            </div>
                        </button>
                    </div>

                    <!-- Gallery title below the mosaic -->
                    <div class="mt-4 text-center">
                        <Link
                            :href="routes.gallery.show(props.featuredGallery.slug)"
                            class="text-lg font-medium text-base-primary hover:text-primary"
                        >
                            {{ props.featuredGallery.title }}
                        </Link>
                        <p
                            v-if="props.featuredGallery.description"
                            class="mt-1 text-sm text-base-muted"
                        >
                            {{ props.featuredGallery.description }}
                        </p>
                    </div>
                </div>

                <!-- Gallery exists but has no photos yet -->
                <div
                    v-else-if="props.featuredGallery && displayPhotos.length === 0"
                    class="rounded-lg border border-default bg-muted p-8 text-center"
                >
                    <svg
                        class="mx-auto h-12 w-12 text-base-muted"
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
                    <h3 class="mt-4 text-lg font-medium text-base-primary">
                        {{ props.featuredGallery.title }}
                    </h3>
                    <p
                        v-if="props.featuredGallery.description"
                        class="mt-1 text-sm text-base-muted"
                    >
                        {{ props.featuredGallery.description }}
                    </p>
                    <p class="mt-2 text-sm text-base-muted">
                        {{ t('gallery.noPhotosYet') }}
                    </p>
                    <Link
                        :href="routes.gallery.show(props.featuredGallery.slug)"
                        class="mt-4 inline-block text-sm font-medium text-primary hover:opacity-80"
                    >
                        {{ t('common.viewAll') }} &rarr;
                    </Link>
                </div>

                <!-- No gallery at all -->
                <aside
                    v-else
                    role="complementary"
                    class="rounded-lg border-2 border-dashed border-default p-8 text-center"
                >
                    <svg
                        class="mx-auto h-8 w-8 text-base-muted"
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
                    <p class="mt-4 text-base-muted">
                        {{ t('home.noGallery') }}
                    </p>
                </aside>
            </section>
        </div>

        <!-- Lightbox -->
        <PhotoLightbox
            :photos="allPhotos"
            :current-index="currentIndex"
            :is-open="isOpen"
            @close="close"
            @next="next"
            @prev="prev"
        />
    </DefaultLayout>
</template>
