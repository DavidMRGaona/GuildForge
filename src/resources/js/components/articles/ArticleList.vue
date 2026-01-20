<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { Article } from '@/types/models';
import ArticleCard from './ArticleCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
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

    <EmptyState
        v-else
        icon="document"
        :title="t('common.noResults')"
        :description="t('articles.noArticles')"
    />
</template>
