<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import KnowledgeCategoryCard from '@/components/knowledge/KnowledgeCategoryCard.vue';
import KnowledgeGuideList from '@/components/knowledge/KnowledgeGuideList.vue';
import KnowledgeSearchBox from '@/components/knowledge/KnowledgeSearchBox.vue';
import type {
    KnowledgeCategory,
    KnowledgeGuideSummary,
    KnowledgeSearchHit,
} from '@/types/knowledge';

interface Props {
    home: {
        categories: KnowledgeCategory[];
        featured: KnowledgeGuideSummary[];
    };
    query: string;
    results: KnowledgeSearchHit[] | null;
}

const props = defineProps<Props>();

const { t } = useI18n();

const categoryLabels = computed<Record<string, string>>(() =>
    Object.fromEntries(props.home.categories.map((category) => [category.id, category.label]))
);
</script>

<template>
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-8 text-center">
            <h1 class="mb-4 text-3xl font-bold text-base-primary">
                {{ t('knowledge.heroTitle') }}
            </h1>
            <div class="mx-auto max-w-2xl text-left">
                <KnowledgeSearchBox :initial-query="query" large />
            </div>
        </header>

        <section v-if="results !== null" class="mb-10" aria-labelledby="kb-results">
            <h2 id="kb-results" class="mb-1 text-xl font-semibold text-base-primary">
                {{ t('knowledge.resultsFor', { query }) }}
            </h2>
            <p class="mb-4 text-sm text-base-muted" role="status">
                {{ t('knowledge.resultsCount', { count: results.length }, results.length) }}
            </p>
            <KnowledgeGuideList
                v-if="results.length > 0"
                :hits="results"
                :category-labels="categoryLabels"
            />
            <p v-else class="rounded-lg bg-muted p-6 text-center text-base-secondary">
                {{ t('knowledge.noResults', { query }) }}
            </p>
        </section>

        <section v-if="home.featured.length > 0" class="mb-10" aria-labelledby="kb-start">
            <h2 id="kb-start" class="mb-3 text-xl font-semibold text-base-primary">
                {{ t('knowledge.gettingStarted') }}
            </h2>
            <KnowledgeGuideList :guides="home.featured" />
        </section>

        <section aria-labelledby="kb-categories">
            <h2 id="kb-categories" class="mb-3 text-xl font-semibold text-base-primary">
                {{ t('knowledge.categories') }}
            </h2>
            <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="category in home.categories" :key="category.id">
                    <KnowledgeCategoryCard :category="category" />
                </li>
            </ul>
        </section>
    </div>
</template>
