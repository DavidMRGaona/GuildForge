<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Event, Article } from '@/types';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import SearchInput from '@/components/search/SearchInput.vue';
import EventCard from '@/components/events/EventCard.vue';
import ArticleCard from '@/components/articles/ArticleCard.vue';

interface Props {
    query: string;
    events: Event[];
    articles: Article[];
    error?: string | null;
}

const props = defineProps<Props>();

const { t } = useI18n();

const hasResults = computed(() => props.events.length > 0 || props.articles.length > 0);

function pluralize(count: number, key: string): string {
    return count === 1 ? t(key, { count }, 1) : t(key, { count }, 2);
}
</script>

<template>
    <Head :title="t('search.title')">
        <meta name="robots" content="noindex,nofollow" />
    </Head>

    <DefaultLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Page Title -->
            <h1 class="mb-6 text-3xl font-bold text-base-primary">
                {{ t('search.title') }}
            </h1>

            <!-- Search Input -->
            <div class="mb-8">
                <SearchInput :initial-query="query" :auto-focus="true" />
            </div>

            <!-- Results for Query -->
            <div v-if="query" class="mb-6">
                <h2 class="text-xl font-semibold text-base-secondary">
                    {{ t('search.resultsFor', { query }) }}
                </h2>
            </div>

            <!-- Min Chars Warning -->
            <div
                v-if="error === 'minChars'"
                class="rounded-lg bg-primary-50 p-4 text-primary-800 dark:bg-primary-900/20 dark:text-primary-300"
            >
                <p class="flex items-center gap-2">
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    {{ t('search.minCharsWarning') }}
                </p>
            </div>

            <!-- No Results -->
            <div v-else-if="query && !hasResults" class="rounded-lg bg-muted p-8 text-center">
                <svg
                    class="mx-auto mb-4 h-16 w-16 text-neutral-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
                <p class="text-lg text-base-secondary">
                    {{ t('search.noResults', { query }) }}
                </p>
            </div>

            <!-- Events Section -->
            <section v-if="events.length > 0" class="mb-12">
                <h2 class="mb-4 text-2xl font-bold text-base-primary">
                    {{ pluralize(events.length, 'search.eventsFound') }}
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <EventCard v-for="event in events" :key="event.id" :event="event" />
                </div>
            </section>

            <!-- Articles Section -->
            <section v-if="articles.length > 0" class="mb-12">
                <h2 class="mb-4 text-2xl font-bold text-base-primary">
                    {{ pluralize(articles.length, 'search.articlesFound') }}
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <ArticleCard v-for="article in articles" :key="article.id" :article="article" />
                </div>
            </section>
        </div>
    </DefaultLayout>
</template>
