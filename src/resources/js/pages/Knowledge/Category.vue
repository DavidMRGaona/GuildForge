<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import KnowledgeGuideList from '@/components/knowledge/KnowledgeGuideList.vue';
import KnowledgeSearchBox from '@/components/knowledge/KnowledgeSearchBox.vue';
import { useRoutes } from '@/composables/useRoutes';
import type { KnowledgeCategory, KnowledgeGuideSummary } from '@/types/knowledge';

defineProps<{
    page: {
        category: KnowledgeCategory;
        guides: KnowledgeGuideSummary[];
    };
}>();

const { t } = useI18n();
const routes = useRoutes();
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <nav :aria-label="t('knowledge.breadcrumb')" class="mb-4 text-sm text-base-secondary">
            <ol class="flex flex-wrap items-center gap-1">
                <li>
                    <Link
                        :href="routes.knowledge.index"
                        class="underline hover:text-base-primary"
                        >{{ t('knowledge.help') }}</Link
                    >
                </li>
                <li aria-hidden="true">›</li>
                <li aria-current="page">{{ page.category.label }}</li>
            </ol>
        </nav>

        <h1 class="mb-4 text-3xl font-bold text-base-primary">{{ page.category.label }}</h1>

        <div class="mb-6">
            <KnowledgeSearchBox />
        </div>

        <KnowledgeGuideList v-if="page.guides.length > 0" :guides="page.guides" />
        <p v-else class="rounded-lg bg-muted p-6 text-center text-base-secondary">
            {{ t('knowledge.emptyCategory') }}
        </p>
    </div>
</template>
