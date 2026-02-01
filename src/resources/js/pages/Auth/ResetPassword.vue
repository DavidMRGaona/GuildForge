<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useSeo } from '@/composables/useSeo';
import type { ResetPasswordFormData } from '@/types/models';

interface Props {
    token: string;
    email: string;
}

const props = defineProps<Props>();

const { t } = useI18n();

useSeo({
    title: t('auth.resetPassword.title'),
});

const form = useForm<ResetPasswordFormData>({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/restablecer-contrasena', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthLayout :title="t('auth.resetPassword.title')" :subtitle="t('auth.resetPassword.subtitle')">
        <form @submit.prevent="submit" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-base-secondary">
                    {{ t('auth.resetPassword.email') }}
                </label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary placeholder-neutral-400 dark:placeholder-neutral-500 focus:border-primary-500 focus:outline-none focus:ring-primary-500"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-error">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-base-secondary">
                    {{ t('auth.resetPassword.password') }}
                </label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary placeholder-neutral-400 dark:placeholder-neutral-500 focus:border-primary-500 focus:outline-none focus:ring-primary-500"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-error">
                    {{ form.errors.password }}
                </p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-base-secondary">
                    {{ t('auth.resetPassword.passwordConfirm') }}
                </label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary placeholder-neutral-400 dark:placeholder-neutral-500 focus:border-primary-500 focus:outline-none focus:ring-primary-500"
                />
            </div>

            <div>
                <BaseButton
                    type="submit"
                    variant="primary"
                    class="w-full"
                    :disabled="form.processing"
                >
                    {{ form.processing ? t('common.loading') : t('auth.resetPassword.submit') }}
                </BaseButton>
            </div>
        </form>
    </AuthLayout>
</template>
