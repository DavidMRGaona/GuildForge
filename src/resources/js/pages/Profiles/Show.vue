<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { BadgeCheck, Calendar, EyeOff, MailWarning, PencilLine, UserRound } from '@lucide/vue';
import ProfileSection from '@/components/layout/ProfileSection.vue';
import ProfileEditorPanel from '@/components/profileEditor/ProfileEditorPanel.vue';
import ProfileBadges from '@/components/ui/ProfileBadges.vue';
import { useNotifications } from '@/composables/useNotifications';
import { buildAvatarUrl, buildImageUrl } from '@/utils/cloudinary';
import { initialsOf } from '@/utils/initials';
import { isInertiaPage } from '@/utils/inertiaLinks';
import { previewProfile } from '@/utils/profilePreview';
import type { ProfileEditorForm } from '@/types/profileEditor';
import type { FlashMessages } from '@/types';
import type { PublicProfilePageProps } from '@/types/profilePage';

/**
 * A person's public profile (core#741): the band of the account page, with the avatar, the public name,
 * the joining month and the membership badge (the banner, when there is one, as the band's background);
 * below it the counters and the sections the core and the modules add (core#742), in a main and a side
 * column. Whoever reaches this page may see it: the server decided, and resolved every section and
 * counter for this person. The owner also sees why nobody else does, when that is the case, and an
 * invitation to fill an empty profile, and edits it from here (core#743): while the editor is open,
 * the page behind is its preview, and the editor makes the whole page inert.
 */
const props = defineProps<PublicProfilePageProps>();

const { t, locale } = useI18n();

/** The section that leads on narrow screens, before the main column */
const LEADING_SECTION = 'core.about';

const editorOpen = ref(false);
const draft = ref<ProfileEditorForm | null>(null);
const editButton = ref<HTMLButtonElement | null>(null);
/** The button that opened the editor (the header's or the empty state's): focus goes back to it */
let opener: HTMLElement | null = null;

/** What is drawn: the profile, or while editing, how it will look once saved */
const shown = computed(() =>
    editorOpen.value && props.editor && draft.value
        ? previewProfile(props.profile, props.editor, draft.value)
        : props.profile
);

/**
 * What the owner's saves answer («Perfil guardado», «Banner actualizado») as a notice. Every answer
 * brings a new `flash` object, so the same message twice in a row is shown twice.
 */
const page = usePage<{ flash?: FlashMessages }>();
const notifications = useNotifications();
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            notifications.success(flash.success);
        }
    },
    { immediate: true }
);

function openEditor(event: MouseEvent): void {
    opener = event.currentTarget instanceof HTMLElement ? event.currentTarget : editButton.value;
    editorOpen.value = true;
}

function closeEditor(): void {
    editorOpen.value = false;
    draft.value = null;
    void nextTick(() => {
        // The empty state's button is gone once the profile has something: the header's then
        const target = opener?.isConnected ? opener : editButton.value;
        target?.focus();
        opener = null;
    });
}

const header = computed(() => shown.value.header);
const bannerUrl = computed(() =>
    buildImageUrl(header.value.bannerPublicId, {
        width: 1500,
        height: 500,
        crop: 'fill',
        gravity: 'auto',
    })
);
const avatarUrl = computed(() => buildAvatarUrl(header.value.avatarPublicId, 256));
const initials = computed(() => initialsOf(header.value.publicName));
const heading = computed(() => header.value.publicName ?? t('publicProfile.noPublicName'));

/**
 * «junio de 2024». The server sends the instant with the display zone's offset, so the year and month
 * are read from the text itself: a browser in another zone would move the first hours of a month.
 */
const memberSinceMonth = computed(() => /^(\d{4})-(\d{2})/.exec(header.value.memberSince ?? ''));
const memberSince = computed(() => {
    const match = memberSinceMonth.value;

    if (!match) {
        return null;
    }

    return new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, 1)).toLocaleDateString(
        locale.value,
        { month: 'long', year: 'numeric', timeZone: 'UTC' }
    );
});

const stats = computed(() => shown.value.stats);
const mainSections = computed(() => shown.value.sections.main);
const leadingAside = computed(() =>
    shown.value.sections.aside.filter((section) => section.key === LEADING_SECTION)
);
const otherAside = computed(() =>
    shown.value.sections.aside.filter((section) => section.key !== LEADING_SECTION)
);
const hasSections = computed(
    () => mainSections.value.length > 0 || shown.value.sections.aside.length > 0
);
const isEmpty = computed(() => stats.value.length === 0 && !hasSections.value);
const twoColumns = computed(
    () => mainSections.value.length > 0 && shown.value.sections.aside.length > 0
);

const numberFormat = computed(() => new Intl.NumberFormat(locale.value));
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

                    <div class="min-w-0 flex-1 text-center sm:text-left">
                        <h1
                            id="profile-name"
                            class="text-2xl font-bold break-words sm:text-3xl"
                            :class="[
                                bannerUrl ? 'text-white' : 'text-base-primary',
                                { 'italic opacity-80': !header.publicName },
                            ]"
                        >
                            {{ heading }}
                        </h1>
                        <ProfileBadges
                            v-if="header.badges.length > 0"
                            :badges="header.badges"
                            class="mt-3 justify-center sm:justify-start"
                        />
                        <p class="mt-2 flex flex-wrap justify-center gap-2 sm:justify-start">
                            <span
                                v-if="memberSince"
                                class="inline-flex items-center gap-1.5 rounded-full border border-default bg-surface px-3 py-1 text-sm text-base-secondary"
                            >
                                <Calendar class="h-4 w-4" aria-hidden="true" />
                                <time :datetime="memberSinceMonth?.[0] ?? undefined">
                                    {{ t('publicProfile.memberSince', { date: memberSince }) }}
                                </time>
                            </span>
                            <span
                                v-if="header.showsMembership"
                                class="inline-flex items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-3 py-1 text-sm font-medium text-primary-800 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-200"
                                data-testid="profile-membership"
                            >
                                <BadgeCheck class="h-4 w-4" aria-hidden="true" />
                                {{ t('publicProfile.membershipBadge') }}
                            </span>
                        </p>
                    </div>

                    <button
                        v-if="editor"
                        ref="editButton"
                        type="button"
                        class="inline-flex shrink-0 items-center gap-2 rounded-md bg-surface px-4 py-2 text-sm font-medium text-base-primary shadow-sm inset-ring inset-ring-(--color-border) transition-colors hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-page) motion-reduce:transition-none"
                        :aria-expanded="editorOpen"
                        aria-haspopup="dialog"
                        data-testid="profile-edit"
                        @click="openEditor"
                    >
                        <PencilLine class="h-4 w-4" aria-hidden="true" />
                        {{ t('publicProfile.editProfile') }}
                    </button>
                </div>
            </header>
        </div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- The owner must verify the email before editing: the routes that save refuse until then -->
            <div v-if="notices.verifyEmail && verifyEmailUrl" class="mt-6">
                <div
                    class="flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-100"
                    role="status"
                    data-testid="profile-verify-notice"
                >
                    <div class="flex items-start gap-2">
                        <MailWarning class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                        <p>{{ t('publicProfile.verifyEmail') }}</p>
                    </div>
                    <Link
                        :href="verifyEmailUrl"
                        class="inline-flex shrink-0 items-center justify-center rounded-md px-3 py-1.5 font-medium underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2 focus-visible:ring-offset-amber-50 dark:focus-visible:ring-amber-300 dark:focus-visible:ring-offset-amber-950"
                    >
                        {{ t('publicProfile.verifyEmailAction') }}
                    </Link>
                </div>
            </div>

            <!-- Only the owner ever gets these: nobody else reaches a profile in either state -->
            <div v-if="notices.choosePublicName || notices.onlyOwner" class="mt-6">
                <div
                    class="flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-100"
                    role="status"
                    data-testid="profile-owner-notice"
                >
                    <div class="flex items-start gap-2">
                        <EyeOff class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                        <div class="space-y-1">
                            <p v-if="notices.onlyOwner">{{ t('publicProfile.onlyOwner') }}</p>
                            <p v-if="notices.choosePublicName">
                                {{ t('publicProfile.choosePublicName') }}
                            </p>
                        </div>
                    </div>
                    <Link
                        v-if="notices.choosePublicName && accountUrl"
                        :href="accountUrl"
                        class="inline-flex shrink-0 items-center justify-center rounded-md px-3 py-1.5 font-medium underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2 focus-visible:ring-offset-amber-50 dark:focus-visible:ring-amber-300 dark:focus-visible:ring-offset-amber-950"
                    >
                        {{ t('publicProfile.choosePublicNameAction') }}
                    </Link>
                </div>
            </div>

            <!--
                The counters, in the owner's order; a row to swipe on a phone. The row is a named region
                that takes the keyboard focus, so it can be scrolled with the arrow keys too.
            -->
            <div
                v-if="stats.length > 0"
                role="region"
                :aria-label="t('publicProfile.statsLabel')"
                tabindex="0"
                class="-mx-4 mt-8 snap-x scroll-px-4 overflow-x-auto rounded-lg px-4 py-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 sm:mx-0 sm:overflow-visible sm:px-0"
                data-testid="profile-stats"
            >
                <ul class="flex gap-3 sm:flex-wrap">
                    <li v-for="stat in stats" :key="stat.key" class="shrink-0 snap-start">
                        <component
                            :is="stat.url ? (isInertiaPage(stat.url) ? Link : 'a') : 'div'"
                            :href="stat.url ?? undefined"
                            class="flex min-w-28 flex-col rounded-lg border border-default bg-surface px-4 py-2"
                            :class="{
                                'transition-colors hover:border-primary-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-page) motion-reduce:transition-none':
                                    stat.url,
                            }"
                        >
                            <span class="text-xl font-semibold text-base-primary tabular-nums">
                                {{ numberFormat.format(stat.value) }}
                            </span>
                            <span class="text-sm text-base-muted">{{ stat.label }}</span>
                        </component>
                    </li>
                </ul>
            </div>

            <!--
                The sections of each region, in the DOM in the order a narrow screen shows them («Sobre
                mí», the main column, the rest of the side one), so focus and reading follow what is seen.
                From lg the grid places them: the main column on the left over two rows, «Sobre mí» and
                the rest of the side column on the right.
            -->
            <div
                v-if="hasSections"
                class="mt-8 flex flex-col gap-6"
                :class="
                    twoColumns ? 'lg:grid lg:grid-cols-3 lg:items-start lg:gap-x-8' : 'max-w-3xl'
                "
            >
                <div
                    v-if="leadingAside.length > 0"
                    class="flex flex-col gap-6"
                    :class="{ 'lg:col-start-3 lg:row-start-1': twoColumns }"
                    data-region="aside"
                >
                    <ProfileSection
                        v-for="section in leadingAside"
                        :key="section.key"
                        :section="section"
                    />
                </div>
                <div
                    v-if="mainSections.length > 0"
                    class="flex flex-col gap-6"
                    :class="{
                        'lg:col-span-2 lg:col-start-1 lg:row-span-2 lg:row-start-1': twoColumns,
                    }"
                    data-region="main"
                >
                    <ProfileSection
                        v-for="section in mainSections"
                        :key="section.key"
                        :section="section"
                    />
                </div>
                <div
                    v-if="otherAside.length > 0"
                    class="flex flex-col gap-6"
                    :class="
                        twoColumns
                            ? [
                                  'lg:col-start-3',
                                  leadingAside.length > 0 ? 'lg:row-start-2' : 'lg:row-start-1',
                              ]
                            : []
                    "
                    data-region="aside"
                >
                    <ProfileSection
                        v-for="section in otherAside"
                        :key="section.key"
                        :section="section"
                    />
                </div>
            </div>

            <div v-if="isEmpty" class="mt-8" data-region="empty">
                <div
                    v-if="header.isOwner"
                    class="rounded-lg border border-dashed border-default bg-surface px-6 py-10 text-center"
                    data-testid="profile-empty-owner"
                >
                    <h2 class="text-lg font-medium text-base-primary">
                        {{ t('publicProfile.emptyOwnerTitle') }}
                    </h2>
                    <p class="mx-auto mt-2 max-w-md text-sm text-base-muted">
                        {{ t('publicProfile.emptyOwnerDescription') }}
                    </p>
                    <button
                        v-if="editor"
                        type="button"
                        class="mt-6 inline-flex items-center justify-center gap-2 rounded-md bg-(--button-primary-bg) px-4 py-2 text-sm font-medium text-(--button-primary-text) inset-ring inset-ring-(--button-primary-border) transition-colors hover:bg-(--button-primary-hover-bg) hover:text-(--button-primary-hover-text) focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-surface) motion-reduce:transition-none"
                        data-testid="profile-empty-edit"
                        @click="openEditor"
                    >
                        <PencilLine class="h-4 w-4" aria-hidden="true" />
                        {{ t('publicProfile.editProfile') }}
                    </button>
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

        <!-- Teleported to the body: outside the page made inert behind it -->
        <ProfileEditorPanel
            v-if="editor && editorOpen"
            :editor="editor"
            @preview="draft = $event"
            @close="closeEditor"
        />
    </article>
</template>
