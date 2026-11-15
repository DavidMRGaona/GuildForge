<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { BadgeCheck, Calendar, EyeOff, PencilLine, UserRound } from '@lucide/vue';
import { buildAvatarUrl, buildImageUrl } from '@/utils/cloudinary';
import { initialsOf } from '@/utils/initials';
import type { PublicProfilePageProps } from '@/types/profilePage';

/**
 * A person's public profile (core#741): the band of the account page, with the avatar, the public name,
 * the joining month and the membership badge (the banner, when there is one, as the band's background);
 * below it the counters and the sections modules add, in a main and a side column. Whoever reaches this page may see it: the server decided. The owner also
 * sees why nobody else does, when that is the case, and an invitation to fill an empty profile.
 */
const props = defineProps<PublicProfilePageProps>();

const { t, locale } = useI18n();

const bannerUrl = computed(() =>
    buildImageUrl(props.profile.bannerPublicId, {
        width: 1500,
        height: 500,
        crop: 'fill',
        gravity: 'auto',
    })
);
const avatarUrl = computed(() => buildAvatarUrl(props.profile.avatarPublicId, 256));
const initials = computed(() => initialsOf(props.profile.publicName));
const heading = computed(() => props.profile.publicName ?? t('publicProfile.noPublicName'));

/** «junio de 2024»: the server gives the month in the display zone, so it is read as is */
const memberSince = computed(() => {
    const match = /^(\d{4})-(\d{2})$/.exec(props.profile.memberSince ?? '');

    if (!match) {
        return null;
    }

    const month = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, 1));

    return month.toLocaleDateString(locale.value, {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    });
});
</script>

<template>
    <article class="pb-12 sm:pb-16" aria-labelledby="profile-name">
        <!-- The band of the account page (/perfil); with a banner, the banner is its background -->
        <div class="relative overflow-hidden bg-muted py-8 sm:py-12" data-testid="profile-band">
            <template v-if="bannerUrl">
                <img
                    :src="bannerUrl"
                    alt=""
                    class="absolute inset-0 h-full w-full object-cover"
                    fetchpriority="high"
                    data-testid="profile-banner"
                />
                <div class="absolute inset-0 bg-black/55" aria-hidden="true" />
            </template>

            <header class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:gap-6">
                    <div
                        class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full bg-neutral-200 ring-4 ring-primary-500 sm:h-32 sm:w-32 dark:bg-neutral-700"
                        data-testid="profile-avatar"
                    >
                        <!-- The name is right beside it: nothing to add for a screen reader -->
                        <img
                            v-if="avatarUrl"
                            :src="avatarUrl"
                            alt=""
                            class="h-full w-full object-cover"
                        />
                        <span
                            v-else-if="initials"
                            class="text-3xl font-semibold text-base-secondary sm:text-4xl"
                            aria-hidden="true"
                        >
                            {{ initials }}
                        </span>
                        <UserRound
                            v-else
                            class="h-12 w-12 text-neutral-400 sm:h-16 sm:w-16"
                            aria-hidden="true"
                        />
                    </div>

                    <div class="min-w-0 text-center sm:text-left">
                        <h1
                            id="profile-name"
                            class="text-2xl font-bold break-words sm:text-3xl"
                            :class="[
                                bannerUrl ? 'text-white' : 'text-base-primary',
                                { 'italic opacity-80': !profile.publicName },
                            ]"
                        >
                            {{ heading }}
                        </h1>
                        <p class="mt-2 flex flex-wrap justify-center gap-2 sm:justify-start">
                            <span
                                v-if="memberSince"
                                class="inline-flex items-center gap-1.5 rounded-full border border-default bg-surface px-3 py-1 text-sm text-base-secondary"
                            >
                                <Calendar class="h-4 w-4" aria-hidden="true" />
                                <time :datetime="profile.memberSince ?? undefined">
                                    {{ t('publicProfile.memberSince', { date: memberSince }) }}
                                </time>
                            </span>
                            <span
                                v-if="profile.membershipBadge"
                                class="inline-flex items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-3 py-1 text-sm font-medium text-primary-800 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-200"
                                data-testid="profile-membership"
                            >
                                <BadgeCheck class="h-4 w-4" aria-hidden="true" />
                                {{ t('publicProfile.membershipBadge') }}
                            </span>
                        </p>
                    </div>
                </div>
            </header>
        </div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Only the owner ever gets these: nobody else reaches a profile in either state -->
            <div v-if="notices.choosePublicName || notices.onlyOwner" class="mt-6">
                <div
                    class="flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-100"
                    role="status"
                    data-testid="profile-owner-notice"
                >
                    <p class="flex items-start gap-2">
                        <EyeOff class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                        <span>
                            {{
                                notices.choosePublicName
                                    ? t('publicProfile.choosePublicName')
                                    : t('publicProfile.onlyOwner')
                            }}
                        </span>
                    </p>
                    <Link
                        v-if="notices.choosePublicName && editUrl"
                        :href="editUrl"
                        class="inline-flex shrink-0 items-center justify-center rounded-md px-3 py-1.5 font-medium underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2 focus-visible:ring-offset-amber-50 dark:focus-visible:ring-amber-300 dark:focus-visible:ring-offset-amber-950"
                    >
                        {{ t('publicProfile.choosePublicNameAction') }}
                    </Link>
                </div>
            </div>

            <!-- Counters and the main and side regions: filled by the sections of the profile contract -->
            <div class="mt-8 grid gap-6 lg:grid-cols-3 lg:gap-8">
                <div class="lg:col-span-3" data-region="empty">
                    <div
                        v-if="isOwner"
                        class="rounded-lg border border-dashed border-default bg-surface px-6 py-10 text-center"
                        data-testid="profile-empty-owner"
                    >
                        <h2 class="text-lg font-medium text-base-primary">
                            {{ t('publicProfile.emptyOwnerTitle') }}
                        </h2>
                        <p class="mx-auto mt-2 max-w-md text-sm text-base-muted">
                            {{ t('publicProfile.emptyOwnerDescription') }}
                        </p>
                        <Link
                            v-if="editUrl"
                            :href="editUrl"
                            class="mt-6 inline-flex items-center justify-center gap-2 rounded-md bg-(--button-primary-bg) px-4 py-2 text-sm font-medium text-(--button-primary-text) inset-ring inset-ring-(--button-primary-border) transition-colors hover:bg-(--button-primary-hover-bg) hover:text-(--button-primary-hover-text) focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-surface) motion-reduce:transition-none"
                        >
                            <PencilLine class="h-4 w-4" aria-hidden="true" />
                            {{ t('publicProfile.editProfile') }}
                        </Link>
                    </div>
                    <p
                        v-else
                        class="py-10 text-center text-sm text-base-muted"
                        data-testid="profile-empty-visitor"
                    >
                        {{ t('publicProfile.emptyVisitor') }}
                    </p>
                </div>
            </div>
        </div>
    </article>
</template>
