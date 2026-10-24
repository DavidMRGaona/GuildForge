<script setup lang="ts">
import { Calendar, Camera, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { buildAvatarUrl } from '@/utils/cloudinary';
import { useNotifications } from '@/composables/useNotifications';
import type { User, UpdateProfileFormData } from '@/types/models';
import { VENUE_TIMEZONE } from '@/utils/datetime';

interface Props {
    user: User;
}

const props = defineProps<Props>();
const { t, locale } = useI18n();
const notifications = useNotifications();

const fileInputRef = ref<HTMLInputElement | null>(null);
const avatarPreview = ref<string | null>(null);

const currentAvatarUrl = computed(() => buildAvatarUrl(props.user.avatarPublicId, 128));
const displayAvatarUrl = computed(() => avatarPreview.value ?? currentAvatarUrl.value);
const displayName = computed(() => props.user.displayName ?? props.user.name);

const memberSinceDate = computed(() => {
    const date = new Date(props.user.createdAt);
    return date.toLocaleDateString(locale.value, {
        month: 'long',
        year: 'numeric',
        timeZone: VENUE_TIMEZONE,
    });
});

const avatarForm = useForm<UpdateProfileFormData>({
    name: props.user.name,
    display_name: props.user.displayName,
    email: props.user.email,
    avatar: null,
});

function triggerFileInput(): void {
    fileInputRef.value?.click();
}

function handleAvatarChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        avatarForm.avatar = file;
        avatarPreview.value = URL.createObjectURL(file);
        uploadAvatar();
    }
}

function uploadAvatar(): void {
    avatarForm.post('/perfil', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            avatarPreview.value = null;
            notifications.success(t('auth.profile.avatarUpdated'));
        },
        onError: () => {
            avatarPreview.value = null;
            notifications.error(t('auth.profile.avatarError'));
        },
    });
}
</script>

<template>
    <div class="bg-neutral-100 py-8 dark:bg-muted sm:py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col items-center gap-4 sm:flex-row sm:gap-6">
                <!-- Avatar (clickable) -->
                <button
                    type="button"
                    class="group relative flex h-24 w-24 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-full bg-neutral-200 ring-4 ring-primary-500 transition-all hover:ring-primary-400 dark:bg-neutral-700 sm:h-32 sm:w-32"
                    :disabled="avatarForm.processing"
                    :title="t('auth.profile.changeAvatar')"
                    @click="triggerFileInput"
                >
                    <img
                        v-if="displayAvatarUrl"
                        :src="displayAvatarUrl"
                        :alt="displayName"
                        class="h-full w-full object-cover"
                    />
                    <!-- The silhouette spans y 5-24 of its 24-unit box: the shifted viewBox centres it -->
                    <UserRound
                        v-else
                        class="h-12 w-12 text-neutral-400 sm:h-16 sm:w-16"
                        aria-hidden="true"
                    />

                    <!-- Hover overlay -->
                    <div
                        class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100"
                        :class="{ 'opacity-100': avatarForm.processing }"
                    >
                        <!-- Loading spinner -->
                        <div
                            v-if="avatarForm.processing"
                            class="h-8 w-8 animate-spin rounded-full border-4 border-white border-t-transparent"
                        />
                        <!-- Camera icon -->
                        <Camera
                            v-else
                            class="h-8 w-8 text-white sm:h-10 sm:w-10"
                            aria-hidden="true"
                        />
                    </div>
                </button>

                <!-- Hidden file input -->
                <input
                    ref="fileInputRef"
                    type="file"
                    accept="image/*"
                    class="sr-only"
                    @change="handleAvatarChange"
                />

                <!-- User info -->
                <div class="text-center sm:text-left">
                    <h1 class="text-2xl font-bold text-base-primary sm:text-3xl">
                        {{ displayName }}
                    </h1>
                    <p class="mt-1 text-base-secondary">
                        {{ user.email }}
                    </p>
                    <p
                        class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-neutral-200 px-3 py-1 text-sm text-base-secondary dark:bg-neutral-700"
                    >
                        <Calendar class="h-4 w-4" aria-hidden="true" />
                        {{ t('auth.profile.memberSince', { date: memberSinceDate }) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
