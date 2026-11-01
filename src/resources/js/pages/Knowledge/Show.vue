<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import KnowledgeGuideBadge from '@/components/knowledge/KnowledgeGuideBadge.vue';
import KnowledgeGuideBody from '@/components/knowledge/KnowledgeGuideBody.vue';
import KnowledgeSearchBox from '@/components/knowledge/KnowledgeSearchBox.vue';
import { useRoutes } from '@/composables/useRoutes';
import type { KnowledgeGuide } from '@/types/knowledge';

const props = defineProps<{
    guide: KnowledgeGuide;
}>();

const { t } = useI18n();
const routes = useRoutes();

const others = computed(() =>
    props.guide.siblings.filter((sibling) => sibling.id !== props.guide.id)
);
</script>

<template>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="lg:grid lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-10">
            <article class="min-w-0 lg:col-start-2 lg:row-start-1">
                <nav
                    :aria-label="t('knowledge.breadcrumb')"
                    class="mb-4 text-sm text-base-secondary"
                >
                    <ol class="flex flex-wrap items-center gap-1">
                        <li>
                            <Link
                                :href="routes.knowledge.index"
                                class="underline hover:text-base-primary"
                                >{{ t('knowledge.help') }}</Link
                            >
                        </li>
                        <li aria-hidden="true">›</li>
                        <li>
                            <Link
                                :href="routes.knowledge.category(guide.category.id)"
                                class="underline hover:text-base-primary"
                                >{{ guide.category.label }}</Link
                            >
                        </li>
                    </ol>
                </nav>

                <header class="mb-6">
                    <h1
                        class="flex flex-wrap items-center gap-3 text-3xl font-bold text-base-primary"
                    >
                        {{ guide.title }}
                        <KnowledgeGuideBadge :badge="guide.badge" />
                    </h1>
                    <p v-if="guide.summary" class="mt-2 text-lg text-base-secondary">
                        {{ guide.summary }}
                    </p>
                </header>

                <KnowledgeGuideBody :html="guide.html" />

                <section
                    v-if="guide.related.length > 0"
                    class="mt-10 border-t border-default pt-6"
                    aria-labelledby="kb-related"
                >
                    <h2 id="kb-related" class="mb-2 text-lg font-semibold text-base-primary">
                        {{ t('knowledge.related') }}
                    </h2>
                    <ul class="list-disc space-y-1 pl-5">
                        <li v-for="related in guide.related" :key="related.id">
                            <Link
                                :href="routes.knowledge.show(related.id)"
                                class="text-primary underline hover:text-base-primary"
                            >
                                {{ related.title }}
                            </Link>
                            <KnowledgeGuideBadge :badge="related.badge" class="ml-2" />
                        </li>
                    </ul>
                </section>
            </article>

            <!-- Sidebar: after the guide in the DOM (below it on small screens), left column on large ones -->
            <aside
                class="mt-10 border-t border-default pt-6 lg:col-start-1 lg:row-start-1 lg:mt-0 lg:border-t-0 lg:pt-0"
                aria-labelledby="kb-side-title"
            >
                <div class="mb-4 hidden lg:block">
                    <KnowledgeSearchBox />
                </div>
                <h2 id="kb-side-title" class="mb-2 text-sm font-semibold text-base-muted">
                    {{ t('knowledge.moreIn', { category: guide.category.label }) }}
                </h2>
                <ul class="space-y-1 text-sm">
                    <li v-for="sibling in others" :key="sibling.id">
                        <Link
                            :href="routes.knowledge.show(sibling.id)"
                            class="block rounded px-2 py-1 text-base-secondary hover:bg-muted hover:text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        >
                            {{ sibling.title }}
                        </Link>
                    </li>
                </ul>
                <p class="mt-4 text-sm">
                    <Link
                        :href="routes.knowledge.index"
                        class="text-primary underline hover:text-base-primary"
                    >
                        {{ t('knowledge.allCategories') }}
                    </Link>
                </p>
            </aside>
        </div>
    </div>
</template>
