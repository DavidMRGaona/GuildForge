<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useSeo } from '@/composables/useSeo';
import type { ForgotPasswordFormData } from '@/types/models';
import { computed } from 'vue';

interface FlashProps {
    flash?: { success?: string; error?: string };
}

const { t } = useI18n();
const page = usePage();
const props = page.props as FlashProps;

useSeo({
    title: t('auth.forgotPassword.title'),
});

const form = useForm<ForgotPasswordFormData>({
    email: '',
});

const successMessage = computed(() => props.flash?.success);

const submit = () => {
    form.post('/olvide-contrasena');
};
</script>

<template>
    <AuthLayout :title="t('auth.forgotPassword.title')" :subtitle="t('auth.forgotPassword.subtitle')">
        <div v-if="successMessage" class="mb-4 rounded-md bg-green-50 dark:bg-green-900/30 p-4">
            <p class="text-sm text-green-700 dark:text-green-400">{{ successMessage }}</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                    {{ t('auth.forgotPassword.email') }}
                </label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 placeholder-stone-400 dark:placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <BaseButton
                    type="submit"
                    variant="primary"
                    class="w-full"
                    :disabled="form.processing"
                >
                    {{ form.processing ? t('common.loading') : t('auth.forgotPassword.submit') }}
                </BaseButton>
            </div>
        </form>
    </AuthLayout>
</template>
