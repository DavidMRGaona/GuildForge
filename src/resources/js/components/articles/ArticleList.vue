<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { Article } from '@/types/models';
import ArticleCard from './ArticleCard.vue';
import { useGridLayout, type GridColumns } from '@/composables/useGridLayout';

interface Props {
    articles: Article[];
    columns?: GridColumns;
}

const props = withDefaults(defineProps<Props>(), {
    columns: 3,
});

const { t } = useI18n();

const { gridClasses } = useGridLayout(() => props.columns);
</script>

<template>
    <div v-if="props.articles.length > 0" class="grid gap-6" :class="gridClasses">
        <ArticleCard v-for="article in props.articles" :key="article.id" :article="article" />
    </div>

    <div v-else class="rounded-lg bg-white p-12 text-center shadow">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"
            />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">{{ t('common.noResults') }}</h3>
        <p class="mt-2 text-sm text-gray-500">{{ t('articles.noArticles') }}</p>
    </div>
</template>
