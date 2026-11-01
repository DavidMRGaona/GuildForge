<script setup lang="ts">
import { Info, Search } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Link } from '@inertiajs/vue3';
import type { Event, Article } from '@/types';
import type { SearchSource } from '@/types/search';
import SearchInput from '@/components/search/SearchInput.vue';
import EventCard from '@/components/events/EventCard.vue';
import ArticleCard from '@/components/articles/ArticleCard.vue';
import KnowledgeSnippet from '@/components/knowledge/KnowledgeSnippet.vue';
import HelpLink from '@/components/knowledge/HelpLink.vue';

interface Props {
    query: string;
    events: Event[];
    articles: Article[];
    sources?: SearchSource[];
    error?: string | null;
}

const props = withDefaults(defineProps<Props>(), { sources: () => [], error: null });

const { t } = useI18n();

const hasResults = computed(
    () => props.events.length > 0 || props.articles.length > 0 || props.sources.length > 0
);

function pluralize(count: number, key: string): string {
    return count === 1 ? t(key, { count }, 1) : t(key, { count }, 2);
}
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <!-- Page Title -->
        <div class="mb-6 flex items-center gap-3">
            <h1 class="text-3xl font-bold text-base-primary">
                {{ t('search.title') }}
            </h1>
            <HelpLink guide="core.search.site-search" />
        </div>

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
                <Info class="h-5 w-5" aria-hidden="true" />
                {{ t('search.minCharsWarning') }}
            </p>
        </div>

        <!-- No Results -->
        <div v-else-if="query && !hasResults" class="rounded-lg bg-muted p-8 text-center">
            <Search class="mx-auto mb-4 h-16 w-16 text-neutral-400" aria-hidden="true" />
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

        <!-- Extra sources (help centre, ...) -->
        <section
            v-for="source in sources"
            :key="source.key"
            class="mb-12"
            :aria-labelledby="`search-source-${source.key}`"
        >
            <h2
                :id="`search-source-${source.key}`"
                class="mb-4 text-2xl font-bold text-base-primary"
            >
                {{ source.label }}
            </h2>
            <ul class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <li v-for="result in source.results" :key="result.url">
                    <Link
                        :href="result.url"
                        class="block rounded-lg border border-default bg-surface p-4 transition-colors hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    >
                        <span class="block font-semibold text-base-primary">{{
                            result.title
                        }}</span>
                        <span v-if="result.context" class="block text-sm text-base-secondary">
                            {{ result.context }}
                        </span>
                        <KnowledgeSnippet
                            v-if="result.snippet.length > 0"
                            :segments="result.snippet"
                            class="mt-2 block text-sm text-base-secondary"
                        />
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
