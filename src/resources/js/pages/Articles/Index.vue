<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Article, PaginatedResponse, Tag } from '@/types';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import ArticleList from '@/components/articles/ArticleList.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import TagFilter from '@/components/ui/TagFilter.vue';
import { useSeo } from '@/composables/useSeo';
import { usePagination } from '@/composables/usePagination';

interface Props {
    articles: PaginatedResponse<Article>;
    tags: Tag[];
    currentTags: string[];
}

const props = withDefaults(defineProps<Props>(), {
    tags: () => [],
    currentTags: () => [],
});

const { t } = useI18n();

useSeo({
    title: t('articles.title'),
    description: t('articles.subtitle'),
});

const isNavigating = ref(false);

const { firstItemNumber, lastItemNumber, hasPagination, goToPrev, goToNext, canGoPrev, canGoNext } =
    usePagination(() => props.articles);

function handlePrev(): void {
    isNavigating.value = true;
    goToPrev();
}

function handleNext(): void {
    isNavigating.value = true;
    goToNext();
}
</script>

<template>
    <DefaultLayout>
        <div class="bg-white shadow dark:bg-stone-800 dark:shadow-stone-900/50">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
                    {{ t('articles.title') }}
                </h1>
                <p class="mt-2 text-lg text-stone-600 dark:text-stone-400">
                    {{ t('articles.subtitle') }}
                </p>
            </div>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <TagFilter
                :tags="props.tags"
                :current-tags="props.currentTags"
                base-path="/articulos"
                class="mb-6"
            />

            <ArticleList :articles="props.articles.data" />

            <div
                v-if="hasPagination"
                class="mt-8 flex items-center justify-between border-t border-stone-200 pt-6 dark:border-stone-700"
            >
                <p class="text-sm text-stone-700 dark:text-stone-300">
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
        </main>
    </DefaultLayout>
</template>
