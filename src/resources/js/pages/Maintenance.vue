<script setup lang="ts">
import { setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import type { AuthLayoutProps } from '@/types/layouts';

interface Props {
    message: string | null;
}

const props = defineProps<Props>();

const { t } = useI18n();

defineOptions({ layout: AuthLayout });
setLayoutProps<AuthLayoutProps>({
    title: t('maintenance.title'),
    showBackLink: false,
});

const message = computed(() => props.message ?? t('maintenance.defaultMessage'));
</script>

<template>
    <p class="text-center text-base-secondary whitespace-pre-line">{{ message }}</p>
</template>
