<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useSeo } from '@/composables/useSeo';
import { useAuth } from '@/composables/useAuth';
import { useNotifications } from '@/composables/useNotifications';
import { useRoutes } from '@/composables/useRoutes';
import type { RegisterFormData } from '@/types/models';

const { t } = useI18n();
const { authSettings } = useAuth();
const notifications = useNotifications();
const routes = useRoutes();

useSeo({
    title: t('auth.register.title'),
});

const form = useForm<RegisterFormData>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(routes.auth.register, {
        onFinish: () => form.reset('password', 'password_confirmation'),
        onError: () => notifications.error(t('auth.register.error')),
    });
};
</script>

<template>
    <AuthLayout :title="t('auth.register.title')" :subtitle="t('auth.register.subtitle')">
        <form @submit.prevent="submit" class="space-y-6">
            <div>
                <label for="name" class="block text-sm font-medium text-base-secondary">
                    {{ t('auth.register.name') }}
                </label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    autocomplete="name"
                    class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary placeholder-neutral-400 dark:placeholder-neutral-500 focus:border-primary-500 focus:outline-none focus:ring-primary-500"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-error">
                    {{ form.errors.name }}
                </p>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-base-secondary">
                    {{ t('auth.register.email') }}
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
                    {{ t('auth.register.password') }}
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
                    {{ t('auth.register.passwordConfirm') }}
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
                    {{ form.processing ? t('common.loading') : t('auth.register.submit') }}
                </BaseButton>
            </div>

            <p v-if="authSettings.loginEnabled" class="text-center text-sm text-base-secondary">
                {{ t('auth.register.hasAccount') }}
                <Link :href="routes.auth.login" class="font-medium text-primary hover:opacity-80">
                    {{ t('auth.register.loginLink') }}
                </Link>
            </p>
        </form>
    </AuthLayout>
</template>
