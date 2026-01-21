<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useSeo } from '@/composables/useSeo';

interface FlashProps {
    flash?: { success?: string };
}

const { t } = useI18n();
const page = usePage();
const props = page.props as FlashProps;

useSeo({
    title: t('auth.verifyEmail.title'),
});

const form = useForm({});
const successMessage = computed(() => props.flash?.success);

const resend = () => {
    form.post('/verificar-email/reenviar');
};
</script>

<template>
    <AuthLayout :title="t('auth.verifyEmail.title')" :subtitle="t('auth.verifyEmail.subtitle')">
        <div v-if="successMessage" class="mb-4 rounded-md bg-green-50 dark:bg-green-900/30 p-4">
            <p class="text-sm text-green-700 dark:text-green-400">{{ successMessage }}</p>
        </div>

        <div class="space-y-6">
            <p class="text-sm text-stone-600 dark:text-stone-400">
                {{ t('auth.verifyEmail.description') }}
            </p>

            <BaseButton
                variant="primary"
                class="w-full"
                :disabled="form.processing"
                @click="resend"
            >
                {{ form.processing ? t('common.loading') : t('auth.verifyEmail.resend') }}
            </BaseButton>
        </div>
    </AuthLayout>
</template>
