<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Event, Article, Gallery } from '@/types/models';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import EventCard from '@/components/events/EventCard.vue';
import ArticleCard from '@/components/articles/ArticleCard.vue';
import GalleryCard from '@/components/gallery/GalleryCard.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import CalendarWidget from '@/components/calendar/CalendarWidget.vue';
import { useSeo } from '@/composables/useSeo';

interface Props {
    upcomingEvents: Event[];
    latestArticles: Article[];
    featuredGallery: Gallery | null;
}

const props = defineProps<Props>();

const { t } = useI18n();

useSeo({
    description: t('home.subtitle'),
});
</script>

<template>
    <DefaultLayout>
        <!-- Hero Section -->
        <section role="banner" class="bg-gradient-to-r from-amber-500 to-amber-600 shadow-lg">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <h1 class="text-4xl font-bold tracking-tight text-white sm:text-5xl">
                    {{ t('home.welcome') }}
                </h1>
                <p class="mt-4 text-2xl text-amber-50">
                    {{ t('home.subtitle') }}
                </p>
                <p class="mt-4 max-w-3xl text-lg text-amber-100">
                    {{ t('home.heroDescription') }}
                </p>
                <div class="mt-8">
                    <Link href="/eventos">
                        <BaseButton variant="secondary" size="lg">
                            {{ t('home.viewEvents') }}
                        </BaseButton>
                    </Link>
                </div>
            </div>
        </section>

        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <!-- Upcoming Events Section -->
            <section class="mb-16">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-3xl font-bold tracking-tight text-gray-900">
                        {{ t('home.upcomingEvents') }}
                    </h2>
                    <Link
                        v-if="props.upcomingEvents.length > 0"
                        href="/eventos"
                        class="text-sm font-medium text-amber-600 hover:text-amber-700"
                    >
                        {{ t('common.viewAll') }} &rarr;
                    </Link>
                </div>

                <div
                    v-if="props.upcomingEvents.length > 0"
                    class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
                >
                    <EventCard
                        v-for="event in props.upcomingEvents"
                        :key="event.id"
                        :event="event"
                    />
                </div>

                <aside
                    v-else
                    role="complementary"
                    class="rounded-lg border-2 border-dashed border-gray-300 p-12 text-center"
                >
                    <svg
                        class="mx-auto h-12 w-12 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                    <p class="mt-4 text-gray-500">
                        {{ t('home.noEvents') }}
                    </p>
                </aside>
            </section>

            <!-- Calendar Widget Section -->
            <section class="mb-16">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-3xl font-bold tracking-tight text-gray-900">
                        {{ t('calendar.title') }}
                    </h2>
                    <Link
                        href="/calendario"
                        class="text-sm font-medium text-amber-600 hover:text-amber-700"
                    >
                        {{ t('calendar.viewFull') }} &rarr;
                    </Link>
                </div>
                <div class="max-w-md">
                    <CalendarWidget />
                </div>
            </section>

            <!-- Latest Articles Section -->
            <section class="mb-16">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-3xl font-bold tracking-tight text-gray-900">
                        {{ t('articles.latest') }}
                    </h2>
                    <Link
                        v-if="props.latestArticles.length > 0"
                        href="/articulos"
                        class="text-sm font-medium text-amber-600 hover:text-amber-700"
                    >
                        {{ t('common.viewAll') }} &rarr;
                    </Link>
                </div>

                <div
                    v-if="props.latestArticles.length > 0"
                    class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
                >
                    <ArticleCard
                        v-for="article in props.latestArticles"
                        :key="article.id"
                        :article="article"
                    />
                </div>

                <aside
                    v-else
                    role="complementary"
                    class="rounded-lg border-2 border-dashed border-gray-300 p-12 text-center"
                >
                    <svg
                        class="mx-auto h-12 w-12 text-gray-400"
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
                    <p class="mt-4 text-gray-500">
                        {{ t('home.noArticles') }}
                    </p>
                </aside>
            </section>

            <!-- Featured Gallery Section -->
            <section>
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-3xl font-bold tracking-tight text-gray-900">
                        {{ t('home.featuredGallery') }}
                    </h2>
                    <Link
                        v-if="props.featuredGallery"
                        href="/galeria"
                        class="text-sm font-medium text-amber-600 hover:text-amber-700"
                    >
                        {{ t('common.viewAll') }} &rarr;
                    </Link>
                </div>

                <div v-if="props.featuredGallery" class="mx-auto max-w-md">
                    <GalleryCard :gallery="props.featuredGallery" />
                </div>

                <aside
                    v-else
                    role="complementary"
                    class="rounded-lg border-2 border-dashed border-gray-300 p-12 text-center"
                >
                    <svg
                        class="mx-auto h-12 w-12 text-gray-400"
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
                    <p class="mt-4 text-gray-500">
                        {{ t('home.noGallery') }}
                    </p>
                </aside>
            </section>
        </div>
    </DefaultLayout>
</template>
