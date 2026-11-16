<script setup lang="ts">
import { User as UserIcon } from '@lucide/vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseCard from '@/components/ui/BaseCard.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useNotifications } from '@/composables/useNotifications';
import type { User, UpdateProfileFormData } from '@/types/models';

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
    // The pending email, if any: saving the other fields keeps that change, typing the current one cancels it
    email: props.user.pendingEmail ?? props.user.email,
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
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-100 text-primary dark:bg-primary-900/30"
                        >
                            <UserIcon class="h-5 w-5" aria-hidden="true" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-base-primary">
                                {{ t('auth.profile.information') }}
                            </h3>
                        </div>
                    </div>
                </template>

                <form class="space-y-4" @submit.prevent="updateProfile">
                    <div>
                        <label for="name" class="block text-sm font-medium text-base-secondary">
                            {{ t('auth.profile.name') }}
                        </label>
                        <input
                            id="name"
                            v-model="profileForm.name"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary focus:border-primary-500 focus:outline-none focus:ring-primary-500"
                        />
                        <p v-if="profileForm.errors.name" class="mt-1 text-sm text-error">
                            {{ profileForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="display_name"
                            class="block text-sm font-medium text-base-secondary"
                        >
                            {{ t('auth.profile.displayName') }}
                        </label>
                        <input
                            id="display_name"
                            v-model="profileForm.display_name"
                            type="text"
                            class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary focus:border-primary-500 focus:outline-none focus:ring-primary-500"
                        />
                        <p class="mt-1 text-sm text-base-muted">
                            {{ t('auth.profile.displayNameHelp') }}
                        </p>
                        <p v-if="profileForm.errors.display_name" class="mt-1 text-sm text-error">
                            {{ profileForm.errors.display_name }}
                        </p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-base-secondary">
                            {{ t('auth.profile.email') }}
                        </label>
                        <input
                            id="email"
                            v-model="profileForm.email"
                            type="email"
                            required
                            class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary focus:border-primary-500 focus:outline-none focus:ring-primary-500"
                        />
                        <p v-if="profileForm.errors.email" class="mt-1 text-sm text-error">
                            {{ profileForm.errors.email }}
                        </p>
                        <p v-if="props.user.pendingEmail" class="mt-2 text-sm text-primary">
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
        </div>
    </div>
</template>
