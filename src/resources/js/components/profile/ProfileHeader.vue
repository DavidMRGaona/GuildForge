<script setup lang="ts">
import { Calendar, Camera, Eye, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { buildAvatarUrl, buildImageUrl } from '@/utils/cloudinary';
import { useAuth } from '@/composables/useAuth';
import { useNotifications } from '@/composables/useNotifications';
import HelpLink from '@/components/knowledge/HelpLink.vue';
import type { User, UpdateProfileFormData } from '@/types/models';
import { VENUE_TIMEZONE } from '@/utils/datetime';

interface Props {
    user: User;
    /** The banner of the own public profile (core#764): the band's background, as on the profile page */
    bannerPublicId?: string | null;
}

const props = withDefaults(defineProps<Props>(), { bannerPublicId: null });
const { t, locale } = useI18n();
const notifications = useNotifications();
const { user: authUser } = useAuth();

// Where the server says the person's own public profile is (core#745); none for an account without one
const publicProfilePath = computed(() => authUser.value?.publicProfilePath ?? null);

const fileInputRef = ref<HTMLInputElement | null>(null);
const avatarPreview = ref<string | null>(null);

const currentAvatarUrl = computed(() => buildAvatarUrl(props.user.avatarPublicId, 128));
const displayAvatarUrl = computed(() => avatarPreview.value ?? currentAvatarUrl.value);
const displayName = computed(() => props.user.displayName ?? props.user.name);

// Same crop as the band of the public profile, so both pages show the same picture
const bannerUrl = computed(() =>
    buildImageUrl(props.bannerPublicId, {
        width: 1500,
        height: 500,
        crop: 'fill',
        gravity: 'auto',
    })
);
// Over the banner's veil the help links get the surface colour: their grey alone was unreadable there
const helpLinkClass = computed(() => (bannerUrl.value ? 'bg-surface' : undefined));

const memberSinceDate = computed(() => {
    const date = new Date(props.user.createdAt);
    return date.toLocaleDateString(locale.value, {
        month: 'long',
        year: 'numeric',
        timeZone: VENUE_TIMEZONE,
    });
});

// The photo goes alone: sending the account fields with it cancelled a pending email change
const avatarForm = useForm<Pick<UpdateProfileFormData, 'avatar'>>({
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
    <div class="relative overflow-hidden bg-muted py-8 sm:py-12">
        <template v-if="bannerUrl">
            <img
                :src="bannerUrl"
                alt=""
                class="absolute inset-0 h-full w-full object-cover"
                fetchpriority="high"
                data-testid="profile-banner"
            />
            <div
                class="absolute inset-0 bg-black/55"
                aria-hidden="true"
                data-testid="profile-banner-veil"
            />
        </template>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
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
                    <!-- Inline so the help link sits beside the name, centred or left-aligned with it -->
                    <h1
                        class="inline align-middle text-2xl font-bold sm:text-3xl"
                        :class="bannerUrl ? 'text-white' : 'text-base-primary'"
                    >
                        {{ displayName }}
                    </h1>
                    <HelpLink
                        guide="core.account.complete-profile"
                        class="ml-2 align-middle"
                        :class="helpLinkClass"
                    />
                    <p
                        class="mt-1"
                        :class="bannerUrl ? 'text-white' : 'text-base-secondary'"
                        data-testid="profile-email"
                    >
                        {{ user.email }}
                    </p>
                    <div
                        class="mt-2 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 sm:justify-start"
                    >
                        <p
                            class="inline-flex items-center gap-1.5 rounded-full border border-default bg-surface px-3 py-1 text-sm text-base-secondary"
                        >
                            <Calendar class="h-4 w-4" aria-hidden="true" />
                            {{ t('auth.profile.memberSince', { date: memberSinceDate }) }}
                        </p>
                        <!-- The public profile is another page from these settings: say so, and link it -->
                        <span v-if="publicProfilePath" class="inline-flex items-center gap-2">
                            <Link
                                :href="publicProfilePath"
                                class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                                :class="
                                    bannerUrl ? 'rounded-full bg-surface px-3 py-1' : 'rounded-md'
                                "
                            >
                                <Eye class="h-4 w-4 shrink-0" aria-hidden="true" />
                                {{ t('auth.profile.viewPublicProfile') }}
                            </Link>
                            <HelpLink
                                guide="core.site.public-profile"
                                :label="t('auth.profile.publicProfileHelp')"
                                :class="helpLinkClass"
                            />
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
