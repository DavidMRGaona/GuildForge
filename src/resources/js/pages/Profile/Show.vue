<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useSeo } from '@/composables/useSeo';
import { useNotifications } from '@/composables/useNotifications';
import { buildAvatarUrl } from '@/utils/cloudinary';
import type { User, UpdateProfileFormData, ChangePasswordFormData } from '@/types/models';

interface Props {
    user: User;
}

interface FlashProps {
    flash?: { success?: string; error?: string };
}

const props = defineProps<Props>();

const { t } = useI18n();
const page = usePage();
const pageProps = page.props as FlashProps;
const notifications = useNotifications();

useSeo({
    title: t('auth.profile.title'),
});

const successMessage = computed(() => pageProps.flash?.success);
const errorMessage = computed(() => pageProps.flash?.error);

// Profile form
const profileForm = useForm<UpdateProfileFormData>({
    name: props.user.name,
    display_name: props.user.displayName,
    email: props.user.email,
    avatar: null,
});

const avatarPreview = ref<string | null>(null);
const currentAvatarUrl = computed(() => buildAvatarUrl(props.user.avatarPublicId, 128));
const displayAvatarUrl = computed(() => avatarPreview.value ?? currentAvatarUrl.value);

const handleAvatarChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        profileForm.avatar = file;
        avatarPreview.value = URL.createObjectURL(file);
    }
};

const updateProfile = () => {
    profileForm.post('/perfil', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            avatarPreview.value = null;
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
    <DefaultLayout>
        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold text-stone-900 dark:text-stone-100 mb-8">
                {{ t('auth.profile.title') }}
            </h1>

            <!-- Flash messages -->
            <div v-if="successMessage" class="mb-6 rounded-md bg-green-50 dark:bg-green-900/30 p-4">
                <p class="text-sm text-green-700 dark:text-green-400">{{ successMessage }}</p>
            </div>
            <div v-if="errorMessage" class="mb-6 rounded-md bg-red-50 dark:bg-red-900/30 p-4">
                <p class="text-sm text-red-700 dark:text-red-400">{{ errorMessage }}</p>
            </div>

            <!-- Profile Information -->
            <BaseCard :title="t('auth.profile.information')" class="mb-8">
                <form @submit.prevent="updateProfile" class="space-y-6">
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

                    <div>
                        <label
                            for="avatar"
                            class="block text-sm font-medium text-stone-700 dark:text-stone-300"
                        >
                            {{ t('auth.profile.avatar') }}
                        </label>
                        <div class="mt-1 flex items-center gap-4">
                            <div
                                class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700"
                            >
                                <img
                                    v-if="displayAvatarUrl"
                                    :src="displayAvatarUrl"
                                    alt="Avatar"
                                    class="h-full w-full object-cover"
                                />
                                <svg
                                    v-else
                                    class="h-8 w-8 text-stone-400"
                                    fill="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        d="M24 20.993V24H0v-2.996A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z"
                                    />
                                </svg>
                            </div>
                            <input
                                id="avatar"
                                type="file"
                                accept="image/*"
                                class="block w-full text-sm text-stone-500 dark:text-stone-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 dark:file:bg-amber-900/30 dark:file:text-amber-400"
                                @change="handleAvatarChange"
                            />
                        </div>
                        <p
                            v-if="profileForm.errors.avatar"
                            class="mt-1 text-sm text-red-600 dark:text-red-400"
                        >
                            {{ profileForm.errors.avatar }}
                        </p>
                    </div>

                    <div class="flex justify-end">
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

            <!-- Change Password -->
            <BaseCard :title="t('auth.profile.changePassword')">
                <form @submit.prevent="changePassword" class="space-y-6">
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

                    <div class="flex justify-end">
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
    </DefaultLayout>
</template>
