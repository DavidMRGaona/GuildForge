<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { KnowledgeBadge } from '@/types/knowledge';

defineProps<{
    badge: KnowledgeBadge;
}>();

const { t } = useI18n();
</script>

<template>
    <span
        v-if="badge"
        class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold"
        :class="
            badge === 'new'
                ? 'border-success bg-success-light text-base-primary'
                : 'border-info bg-info-light text-base-primary'
        "
    >
        {{ t(`knowledge.badge.${badge}`) }}
    </span>
</template>
