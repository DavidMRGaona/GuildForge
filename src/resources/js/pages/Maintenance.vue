<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useSeo } from '@/composables/useSeo';

interface Props {
    message: string | null;
}

const props = defineProps<Props>();

const { t } = useI18n();

useSeo({
    title: t('maintenance.title'),
});

const message = computed(() => props.message ?? t('maintenance.defaultMessage'));
</script>

<template>
    <AuthLayout :title="t('maintenance.title')" :show-back-link="false">
        <p class="text-center text-base-secondary whitespace-pre-line">{{ message }}</p>
    </AuthLayout>
</template>
