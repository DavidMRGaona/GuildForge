<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Article, PaginatedResponse, Tag } from '@/types';
import ArticleList from '@/components/articles/ArticleList.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import TagFilter from '@/components/ui/TagFilter.vue';
import { usePagination } from '@/composables/usePagination';
import { useRoutes } from '@/composables/useRoutes';
import HelpLink from '@/components/knowledge/HelpLink.vue';

interface Props {
    articles: PaginatedResponse<Article>;
    tags?: Tag[];
    currentTags?: string[];
    /** Whether the person may propose an article (core#579). */
    canPropose?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    tags: () => [],
    currentTags: () => [],
    canPropose: false,
});

const { t } = useI18n();
const routes = useRoutes();

const {
    firstItemNumber,
    lastItemNumber,
    hasPagination,
    canGoPrev,
    canGoNext,
    isNavigating,
    handlePrev,
    handleNext,
} = usePagination(() => props.articles);
</script>

<template>
    <div data-testid="page-band" class="page-band">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                        {{ t('articles.title') }}
                    </h1>
                    <HelpLink guide="core.content.read-articles" />
                </div>
                <Link
                    v-if="canPropose"
                    :href="routes.proposals.articles.create"
                    data-testid="propose-article"
                    class="inline-flex min-h-[44px] shrink-0 items-center justify-center rounded-md bg-(--button-primary-bg) px-4 py-2 font-medium text-(--button-primary-text) inset-ring inset-ring-(--button-primary-border) hover:bg-(--button-primary-hover-bg) hover:text-(--button-primary-hover-text) focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                >
                    {{ t('proposals.proposeArticle') }}
                </Link>
            </div>
            <p class="mt-2 text-lg text-base-secondary">
                {{ t('articles.subtitle') }}
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <TagFilter
            :tags="props.tags"
            :current-tags="props.currentTags"
            :base-path="routes.articles.index"
            class="mb-6"
        />

        <ArticleList :articles="props.articles.data" />

        <div
            v-if="hasPagination"
            class="mt-8 flex items-center justify-between border-t border-default pt-6"
        >
            <p class="text-sm text-base-secondary">
                {{ t('common.showing') }}
                <span class="font-medium">
                    {{ firstItemNumber }}
                </span>
                -
                <span class="font-medium">
                    {{ lastItemNumber }}
                </span>
                {{ t('common.of') }}
                <span class="font-medium">{{ props.articles.meta.total }}</span>
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
    </div>
</template>
