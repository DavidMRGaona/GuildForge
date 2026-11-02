<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Link } from '@inertiajs/vue3';
import { useKnowledgeVisibility } from '@/composables/useKnowledgeVisibility';
import { useRoutes } from '@/composables/useRoutes';

const props = defineProps<{
    guide: string;
    label?: string;
}>();

const { t } = useI18n();
const routes = useRoutes();
const { isVisible } = useKnowledgeVisibility();

const accessibleLabel = computed(() => props.label ?? t('knowledge.contextualHelp'));
</script>

<template>
    <Link
        v-if="isVisible(guide)"
        :href="routes.knowledge.show(guide)"
        :aria-label="accessibleLabel"
        :title="accessibleLabel"
        class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-default text-sm font-semibold text-base-secondary hover:text-primary-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
    >
        <span aria-hidden="true">?</span>
    </Link>
</template>
