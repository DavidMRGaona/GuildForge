<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useRoutes } from '@/composables/useRoutes';
import type { KnowledgeCategory } from '@/types/knowledge';

defineProps<{
    category: KnowledgeCategory;
}>();

const { t } = useI18n();
const routes = useRoutes();
</script>

<template>
    <Link
        :href="routes.knowledge.category(category.id)"
        class="block rounded-lg border border-default bg-surface p-4 transition-colors hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
    >
        <span class="block text-base font-semibold text-base-primary">{{ category.label }}</span>
        <span class="mt-1 block text-sm text-base-muted">
            {{ t('knowledge.guideCount', { count: category.guideCount }, category.guideCount) }}
        </span>
    </Link>
</template>
