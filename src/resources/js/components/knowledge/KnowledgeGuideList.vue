<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useRoutes } from '@/composables/useRoutes';
import KnowledgeGuideBadge from './KnowledgeGuideBadge.vue';
import KnowledgeSnippet from './KnowledgeSnippet.vue';
import type { KnowledgeGuideSummary, KnowledgeSearchHit } from '@/types/knowledge';

withDefaults(
    defineProps<{
        guides?: KnowledgeGuideSummary[];
        hits?: KnowledgeSearchHit[];
        categoryLabels?: Record<string, string>;
    }>(),
    { guides: () => [], hits: () => [], categoryLabels: () => ({}) }
);

const routes = useRoutes();
</script>

<template>
    <ul class="divide-y divide-[var(--color-border)] rounded-lg border border-default bg-surface">
        <li v-for="guide in guides" :key="guide.id">
            <Link
                :href="routes.knowledge.show(guide.id)"
                class="block px-4 py-3 hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
            >
                <span class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-base-primary">{{ guide.title }}</span>
                    <KnowledgeGuideBadge :badge="guide.badge" />
                </span>
                <span v-if="guide.summary" class="mt-0.5 block text-sm text-base-secondary">{{
                    guide.summary
                }}</span>
            </Link>
        </li>
        <li v-for="hit in hits" :key="hit.id">
            <Link
                :href="routes.knowledge.show(hit.id)"
                class="block px-4 py-3 hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
            >
                <span class="block font-semibold text-base-primary">{{ hit.title }}</span>
                <span v-if="categoryLabels[hit.categoryId]" class="block text-xs text-base-muted">{{
                    categoryLabels[hit.categoryId]
                }}</span>
                <KnowledgeSnippet
                    :segments="hit.snippet"
                    class="mt-0.5 block text-sm text-base-secondary"
                />
            </Link>
        </li>
    </ul>
</template>
