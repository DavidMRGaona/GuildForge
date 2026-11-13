<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useRoutes } from '@/composables/useRoutes';

defineProps<{ processing: boolean }>();

defineEmits<{ draft: []; submit: [] }>();

const { t } = useI18n();
const routes = useRoutes();
</script>

<template>
    <div
        class="flex flex-col-reverse gap-3 border-t border-default pt-6 sm:flex-row sm:justify-end"
    >
        <Link
            :href="routes.proposals.mine"
            class="inline-flex min-h-[44px] items-center justify-center rounded-md px-4 py-2 text-base font-medium text-base-secondary hover:bg-muted focus:outline-none focus:ring-2 focus:ring-neutral-400"
        >
            {{ t('common.cancel') }}
        </Link>
        <BaseButton
            type="button"
            variant="secondary"
            :disabled="processing"
            data-testid="save-draft"
            @click="$emit('draft')"
        >
            {{ t('proposals.form.saveDraft') }}
        </BaseButton>
        <BaseButton
            type="button"
            :loading="processing"
            data-testid="submit-review"
            @click="$emit('submit')"
        >
            {{ t('proposals.form.submit') }}
        </BaseButton>
    </div>
</template>
