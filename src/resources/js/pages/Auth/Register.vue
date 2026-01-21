<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useSeo } from '@/composables/useSeo';
import { useAuth } from '@/composables/useAuth';
import type { RegisterFormData } from '@/types/models';

const { t } = useI18n();
const { authSettings } = useAuth();

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
    form.post('/registro', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthLayout :title="t('auth.register.title')" :subtitle="t('auth.register.subtitle')">
        <form @submit.prevent="submit" class="space-y-6">
            <div>
                <label for="name" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                    {{ t('auth.register.name') }}
                </label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    autocomplete="name"
                    class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 placeholder-stone-400 dark:placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.name }}
                </p>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                    {{ t('auth.register.email') }}
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
                <label for="password" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                    {{ t('auth.register.password') }}
                </label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 placeholder-stone-400 dark:placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.password }}
                </p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                    {{ t('auth.register.passwordConfirm') }}
                </label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 placeholder-stone-400 dark:placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
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

            <p v-if="authSettings.loginEnabled" class="text-center text-sm text-stone-600 dark:text-stone-400">
                {{ t('auth.register.hasAccount') }}
                <Link href="/login" class="font-medium text-amber-600 hover:text-amber-500 dark:text-amber-400 dark:hover:text-amber-300">
                    {{ t('auth.register.loginLink') }}
                </Link>
            </p>
        </form>
    </AuthLayout>
</template>
