<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useSeo } from '@/composables/useSeo';
import { useAuth } from '@/composables/useAuth';
import { useNotifications } from '@/composables/useNotifications';
import { useRoutes } from '@/composables/useRoutes';
import type { LoginFormData } from '@/types/models';

const { t } = useI18n();
const { authSettings } = useAuth();
const notifications = useNotifications();
const routes = useRoutes();

useSeo({
    title: t('auth.login.title'),
});

const form = useForm<LoginFormData>({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(routes.auth.login, {
        onFinish: () => form.reset('password'),
        onError: () => notifications.error(t('auth.login.error')),
    });
};
</script>

<template>
    <AuthLayout :title="t('auth.login.title')" :subtitle="t('auth.login.subtitle')">
        <form @submit.prevent="submit" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-base-secondary">
                    {{ t('auth.login.email') }}
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
                    {{ t('auth.login.password') }}
                </label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary placeholder-neutral-400 dark:placeholder-neutral-500 focus:border-primary-500 focus:outline-none focus:ring-primary-500"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-error">
                    {{ form.errors.password }}
                </p>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input
                        id="remember"
                        v-model="form.remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-default text-primary-600 focus:ring-primary-500"
                    />
                    <label for="remember" class="ml-2 block text-sm text-base-secondary">
                        {{ t('auth.login.remember') }}
                    </label>
                </div>

                <Link
                    :href="routes.auth.forgotPassword"
                    class="text-sm font-medium text-primary hover:opacity-80"
                >
                    {{ t('auth.login.forgotPassword') }}
                </Link>
            </div>

            <div>
                <BaseButton
                    type="submit"
                    variant="primary"
                    class="w-full"
                    :disabled="form.processing"
                >
                    {{ form.processing ? t('common.loading') : t('auth.login.submit') }}
                </BaseButton>
            </div>

            <p
                v-if="authSettings.registrationEnabled"
                class="text-center text-sm text-base-secondary"
            >
                {{ t('auth.login.noAccount') }}
                <Link
                    :href="routes.auth.register"
                    class="font-medium text-primary hover:opacity-80"
                >
                    {{ t('auth.login.registerLink') }}
                </Link>
            </p>
        </form>
    </AuthLayout>
</template>
