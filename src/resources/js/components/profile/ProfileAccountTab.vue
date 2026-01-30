<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseCard from '@/components/ui/BaseCard.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useNotifications } from '@/composables/useNotifications';
import type { User, UpdateProfileFormData, ChangePasswordFormData } from '@/types/models';

interface Props {
    user: User;
}

const props = defineProps<Props>();

const { t } = useI18n();
const notifications = useNotifications();

// Profile form
const profileForm = useForm<Omit<UpdateProfileFormData, 'avatar'>>({
    name: props.user.name,
    display_name: props.user.displayName,
    email: props.user.email,
});

const updateProfile = () => {
    profileForm.post('/perfil', {
        preserveScroll: true,
        onSuccess: () => {
            notifications.success(t('auth.profile.profileUpdated'));
        },
        onError: () => notifications.error(t('auth.profile.profileError')),
    });
};

// Password form
const passwordForm = useForm<ChangePasswordFormData>({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const changePassword = () => {
    passwordForm.put('/perfil/contrasena', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            notifications.success(t('auth.profile.passwordUpdated'));
        },
        onError: () => notifications.error(t('auth.profile.passwordError')),
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Grid for desktop: 2 columns -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Profile Information Card -->
            <BaseCard>
                <template #header>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-base-primary">
                                {{ t('auth.profile.information') }}
                            </h3>
                        </div>
                    </div>
                </template>

                <form @submit.prevent="updateProfile" class="space-y-4">
                    <div>
                        <label
                            for="name"
                            class="block text-sm font-medium text-stone-700 dark:text-stone-300"
                        >
                            {{ t('auth.profile.name') }}
                        </label>
                        <input
                            id="name"
                            v-model="profileForm.name"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                        />
                        <p
                            v-if="profileForm.errors.name"
                            class="mt-1 text-sm text-red-600 dark:text-red-400"
                        >
                            {{ profileForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="display_name"
                            class="block text-sm font-medium text-stone-700 dark:text-stone-300"
                        >
                            {{ t('auth.profile.displayName') }}
                        </label>
                        <input
                            id="display_name"
                            v-model="profileForm.display_name"
                            type="text"
                            class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                        />
                        <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">
                            {{ t('auth.profile.displayNameHelp') }}
                        </p>
                        <p
                            v-if="profileForm.errors.display_name"
                            class="mt-1 text-sm text-red-600 dark:text-red-400"
                        >
                            {{ profileForm.errors.display_name }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="email"
                            class="block text-sm font-medium text-stone-700 dark:text-stone-300"
                        >
                            {{ t('auth.profile.email') }}
                        </label>
                        <input
                            id="email"
                            v-model="profileForm.email"
                            type="email"
                            required
                            class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                        />
                        <p
                            v-if="profileForm.errors.email"
                            class="mt-1 text-sm text-red-600 dark:text-red-400"
                        >
                            {{ profileForm.errors.email }}
                        </p>
                        <p
                            v-if="props.user.pendingEmail"
                            class="mt-2 text-sm text-amber-600 dark:text-amber-400"
                        >
                            {{
                                t('auth.profile.pendingEmailNotice', {
                                    email: props.user.pendingEmail,
                                })
                            }}
                        </p>
                    </div>

                    <div class="flex justify-end pt-2">
                        <BaseButton
                            type="submit"
                            variant="primary"
                            :disabled="profileForm.processing"
                        >
                            {{ profileForm.processing ? t('common.loading') : t('common.save') }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>

            <!-- Security Card -->
            <BaseCard>
                <template #header>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-base-primary">
                                {{ t('auth.profile.security') }}
                            </h3>
                        </div>
                    </div>
                </template>

                <form @submit.prevent="changePassword" class="space-y-4">
                    <div>
                        <label
                            for="current_password"
                            class="block text-sm font-medium text-stone-700 dark:text-stone-300"
                        >
                            {{ t('auth.profile.currentPassword') }}
                        </label>
                        <input
                            id="current_password"
                            v-model="passwordForm.current_password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                        />
                        <p
                            v-if="passwordForm.errors.current_password"
                            class="mt-1 text-sm text-red-600 dark:text-red-400"
                        >
                            {{ passwordForm.errors.current_password }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="new_password"
                            class="block text-sm font-medium text-stone-700 dark:text-stone-300"
                        >
                            {{ t('auth.profile.newPassword') }}
                        </label>
                        <input
                            id="new_password"
                            v-model="passwordForm.password"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                        />
                        <p
                            v-if="passwordForm.errors.password"
                            class="mt-1 text-sm text-red-600 dark:text-red-400"
                        >
                            {{ passwordForm.errors.password }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="password_confirmation"
                            class="block text-sm font-medium text-stone-700 dark:text-stone-300"
                        >
                            {{ t('auth.profile.confirmPassword') }}
                        </label>
                        <input
                            id="password_confirmation"
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                        />
                    </div>

                    <div class="flex justify-end pt-2">
                        <BaseButton
                            type="submit"
                            variant="primary"
                            :disabled="passwordForm.processing"
                        >
                            {{
                                passwordForm.processing
                                    ? t('common.loading')
                                    : t('auth.profile.updatePassword')
                            }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>
        </div>
    </div>
</template>
