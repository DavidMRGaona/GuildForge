<script setup lang="ts">
import { Info, Search } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import type { SearchError, SearchHit, SearchTab } from '@/types/search';
import SearchInput from '@/components/search/SearchInput.vue';
import SearchTabs from '@/components/search/SearchTabs.vue';
import SearchResultItem from '@/components/search/SearchResultItem.vue';
import HelpLink from '@/components/knowledge/HelpLink.vue';
import BaseButton from '@/components/ui/BaseButton.vue';

interface Props {
    query: string;
    type?: string | null;
    tabs?: SearchTab[];
    results?: SearchHit[];
    total?: number;
    page?: number;
    hasMore?: boolean;
    error?: SearchError | null;
}

const props = withDefaults(defineProps<Props>(), {
    type: null,
    tabs: () => [],
    results: () => [],
    total: 0,
    page: 1,
    hasMore: false,
    error: null,
});

const { t } = useI18n();

const list = ref<HTMLElement | null>(null);
const isLoadingMore = ref(false);
const loadMoreFailed = ref(false);

// Tabs and new queries keep the page mounted: an old failure must not linger
watch(
    () => [props.query, props.type],
    () => {
        loadMoreFailed.value = false;
    }
);

/** Re-enables the button and says so; returning false keeps Inertia's error dialog away. */
function failLoadingMore(): false {
    isLoadingMore.value = false;
    loadMoreFailed.value = true;

    return false;
}

/**
 * Appends the next page: `results` is a merge prop, so a partial reload adds to it. The address
 * bar keeps `?q=&tipo=`, since reloading `?pagina=2` would show that page alone.
 */
function showMore(): void {
    const firstNewRow = props.results.length;
    isLoadingMore.value = true;
    loadMoreFailed.value = false;

    router.reload({
        data: { pagina: props.page + 1 },
        only: ['results', 'page', 'hasMore'],
        preserveUrl: true,
        onSuccess: () => {
            // Keyboard users carry on from the first row they have not seen yet
            void nextTick(() => {
                list.value?.querySelectorAll<HTMLElement>(':scope > li > a')[firstNewRow]?.focus();
            });
        },
        onError: () => {
            failLoadingMore();
        },
        onHttpException: failLoadingMore,
        onNetworkError: failLoadingMore,
        onFinish: () => {
            isLoadingMore.value = false;
        },
    });
}
</script>

<template>
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center gap-3">
            <h1 class="text-3xl font-bold text-base-primary">
                {{ t('search.title') }}
            </h1>
            <HelpLink guide="core.search.site-search" />
        </div>

        <div class="mb-8">
            <SearchInput :initial-query="query" :auto-focus="true" />
        </div>

        <div v-if="query" class="mb-4">
            <h2 class="text-xl font-semibold text-base-secondary">
                {{ t('search.resultsFor', { query }) }}
            </h2>
        </div>

        <div v-if="error === 'minChars'" class="rounded-lg bg-primary-light p-4 text-base-primary">
            <p class="flex items-center gap-2">
                <Info class="h-5 w-5 shrink-0" aria-hidden="true" />
                {{ t('search.minCharsWarning') }}
            </p>
        </div>

        <div v-else-if="query && results.length === 0" class="rounded-lg bg-muted p-8 text-center">
            <Search class="mx-auto mb-4 h-16 w-16 text-base-muted" aria-hidden="true" />
            <p class="text-lg text-base-secondary">
                {{ t('search.noResults', { query }) }}
            </p>
        </div>

        <SearchTabs v-if="!error && tabs.length > 0" :tabs="tabs" :active="type" :query="query" />

        <!-- Kept in the page while there is a query, so a screen reader hears each new count -->
        <p
            v-if="query && !error"
            role="status"
            data-testid="search-results-count"
            :class="results.length > 0 ? 'mb-2 mt-4 text-sm text-base-muted' : 'sr-only'"
        >
            {{ t('search.resultsCount', { count: total }, total) }}
        </p>

        <div v-if="!error && results.length > 0">
            <ul
                ref="list"
                data-testid="search-results"
                class="-mx-3 divide-y divide-[var(--color-border)] sm:-mx-4"
            >
                <li v-for="hit in results" :key="`${hit.type}:${hit.url}`" class="py-1">
                    <SearchResultItem :hit="hit" />
                </li>
            </ul>

            <div v-if="hasMore" class="mt-6 flex justify-center">
                <BaseButton
                    data-testid="search-show-more"
                    variant="secondary"
                    :loading="isLoadingMore"
                    :disabled="isLoadingMore"
                    @click="showMore"
                >
                    {{ t('search.showMore') }}
                </BaseButton>
            </div>

            <!-- Always in the page, so the failure is announced; it takes room only once it says something -->
            <p
                role="status"
                data-testid="search-show-more-error"
                :class="loadMoreFailed ? 'mt-3 text-center text-sm text-base-secondary' : 'sr-only'"
            >
                <template v-if="loadMoreFailed">{{ t('search.loadMoreError') }}</template>
            </p>
        </div>
    </div>
</template>
