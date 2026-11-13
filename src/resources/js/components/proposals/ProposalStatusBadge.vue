<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ProposalStatus } from '@/types/proposals';

const props = defineProps<{ status: ProposalStatus }>();

const { t } = useI18n();

// Colour never carries the meaning alone: the label always says it
const classes = computed(
    () =>
        ({
            draft: 'bg-muted text-base-secondary',
            pending_review: 'bg-warning-light text-warning',
            approved: 'bg-success-light text-success',
            rejected: 'bg-error-light text-error',
        })[props.status]
);
</script>

<template>
    <span
        data-testid="proposal-status"
        :class="[
            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
            classes,
        ]"
    >
        {{ t(`proposals.status.${status}`) }}
    </span>
</template>
