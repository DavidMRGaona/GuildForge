<script setup lang="ts">
import { defineAsyncComponent, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Mail, Phone, MapPin } from 'lucide-vue-next';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import ContactForm from '@/components/contact/ContactForm.vue';
import { useSeo } from '@/composables/useSeo';
import { buildFullScreenHeroImageUrl } from '@/utils/cloudinary';
import { activityIconMap } from '@/utils/icons';
import type { Activity, JoinStep, SocialMediaLinks } from '@/types/models';

interface Props {
    guildName: string;
    aboutHistory: string;
    contactEmail: string;
    contactPhone: string;
    contactAddress: string;
    aboutHeroImage: string;
    aboutTagline: string;
    activities: Activity[];
    joinSteps: JoinStep[];
    socialFacebook: string;
    socialInstagram: string;
    socialTwitter: string;
    socialDiscord: string;
    socialTiktok: string;
    socialBluesky: string;
    socialTelegram: string;
    location: { name: string; address: string; lat: number; lng: number; zoom: number } | null;
}

const props = defineProps<Props>();

const LocationMap = defineAsyncComponent(() => import('@/components/map/LocationMap.vue'));

const { t } = useI18n();

useSeo({
    title: t('about.title', { appName: props.guildName }),
    description: props.aboutTagline || t('about.subtitle'),
});

const openStreetMapUrl = computed(() => {
    if (!props.location) return 'https://www.openstreetmap.org';
    const { lat, lng, zoom } = props.location;
    return `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=${zoom}/${lat}/${lng}`;
});

const hasJoinSteps = computed(() => props.joinSteps.length > 0);

const socialLinks = computed<SocialMediaLinks>(() => ({
    facebook: props.socialFacebook || undefined,
    instagram: props.socialInstagram || undefined,
    twitter: props.socialTwitter || undefined,
    discord: props.socialDiscord || undefined,
    tiktok: props.socialTiktok || undefined,
    bluesky: props.socialBluesky || undefined,
    telegram: props.socialTelegram || undefined,
}));

const hasSocialLinks = computed(
    () =>
        props.socialFacebook ||
        props.socialInstagram ||
        props.socialTwitter ||
        props.socialDiscord ||
        props.socialTiktok ||
        props.socialBluesky ||
        props.socialTelegram
);

const heroImageUrl = computed(() => buildFullScreenHeroImageUrl(props.aboutHeroImage));
</script>

<template>
    <DefaultLayout>
        <!-- Hero Section -->
        <div
            class="relative h-64 md:h-80 flex items-center justify-center overflow-hidden"
            :class="{ 'bg-gradient-to-r from-primary-500 to-primary-600': !heroImageUrl }"
        >
            <!-- Background Image -->
            <img
                v-if="heroImageUrl"
                :src="heroImageUrl"
                :alt="guildName"
                class="absolute inset-0 w-full h-full object-cover"
            />
            <!-- Dark Overlay -->
            <div
                class="absolute inset-0 bg-black/40"
                :class="{ 'bg-black/50': heroImageUrl }"
            ></div>
            <!-- Content -->
            <div class="relative z-10 text-center px-4">
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-white drop-shadow-lg">
                    {{ t('about.title', { appName: guildName }) }}
                </h1>
                <p v-if="aboutTagline" class="mt-3 text-lg md:text-xl text-white/90 drop-shadow">
                    {{ aboutTagline }}
                </p>
            </div>
        </div>

        <!-- Main Content -->
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Activities Section -->
            <section v-if="activities.length > 0" class="mb-8">
                <h2 class="text-2xl font-bold text-base-primary mb-6">
                    {{ t('about.whatWeDo.title') }}
                </h2>
                <div class="flex flex-wrap justify-center gap-6">
                    <div
                        v-for="(activity, index) in activities"
                        :key="index"
                        class="w-full sm:w-[calc(50%-0.75rem)] lg:w-[calc(33.333%-1rem)] bg-surface rounded-lg shadow-sm border-default p-6 hover:shadow-md transition-shadow"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex-shrink-0 rounded-lg bg-primary-100 dark:bg-primary-900/40 p-3 text-primary-600 dark:text-primary-400"
                            >
                                <component
                                    :is="activityIconMap[activity.icon]"
                                    class="h-6 w-6"
                                    aria-hidden="true"
                                />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-base-primary mb-1">
                                    {{ activity.title }}
                                </h3>
                                <p class="text-sm text-base-secondary leading-relaxed">
                                    {{ activity.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- History Section -->
            <BaseCard v-if="aboutHistory" :title="t('about.history.title')" class="mb-8">
                <!--
                    SECURITY: v-html is used to render rich text content from site settings.
                    Content MUST be sanitized server-side before storage in the database.
                    XSS RISK: If content is not properly sanitized, this could execute malicious scripts.
                    @see App\Filament\Pages\SiteSettings - about_history field validation
                    @see App\Infrastructure\Services\SettingsService - settings persistence
                -->
                <div
                    v-html="aboutHistory"
                    class="prose prose-stone dark:prose-invert max-w-none"
                ></div>
            </BaseCard>

            <!-- Join Section -->
            <section v-if="hasJoinSteps" class="space-y-12 mb-8">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-base-primary">
                        {{ $t('about.join.title') }}
                    </h2>
                </div>

                <!-- Desktop: Horizontal Timeline -->
                <div class="hidden md:block">
                    <div class="relative">
                        <!-- Connection line -->
                        <div
                            class="absolute left-0 right-0 top-6 h-0.5 bg-primary-200 dark:bg-primary-900"
                        />

                        <!-- Steps -->
                        <div class="relative flex justify-between">
                            <div
                                v-for="(step, index) in joinSteps"
                                :key="index"
                                class="flex flex-col items-center text-center"
                                :style="{ width: `${100 / joinSteps.length}%` }"
                            >
                                <!-- Number circle -->
                                <div
                                    class="relative z-10 flex h-12 w-12 items-center justify-center rounded-full bg-primary-500 text-xl font-bold text-white shadow-lg"
                                >
                                    {{ index + 1 }}
                                </div>

                                <!-- Content -->
                                <div class="mt-4 max-w-[200px]">
                                    <h3 class="font-semibold text-base-primary">
                                        {{ step.title }}
                                    </h3>
                                    <p
                                        v-if="step.description"
                                        class="mt-1 text-sm text-base-secondary"
                                    >
                                        {{ step.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile: Vertical Timeline -->
                <div class="md:hidden">
                    <div class="relative ml-6">
                        <!-- Connection line -->
                        <div
                            class="absolute bottom-0 left-0 top-0 w-0.5 bg-primary-200 dark:bg-primary-900"
                        />

                        <!-- Steps -->
                        <div class="space-y-8">
                            <div
                                v-for="(step, index) in joinSteps"
                                :key="index"
                                class="relative flex items-start gap-4"
                            >
                                <!-- Number circle -->
                                <div
                                    class="relative z-10 -ml-6 flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-primary-500 text-xl font-bold text-white shadow-lg"
                                >
                                    {{ index + 1 }}
                                </div>

                                <!-- Content -->
                                <div class="pt-2">
                                    <h3 class="font-semibold text-base-primary">
                                        {{ step.title }}
                                    </h3>
                                    <p
                                        v-if="step.description"
                                        class="mt-1 text-sm text-base-secondary"
                                    >
                                        {{ step.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Contact Section - Two Column Layout -->
            <section id="contact-section" class="mb-8">
                <h2 class="text-2xl font-bold text-base-primary mb-6">
                    {{ t('about.contact.title') }}
                </h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-stretch">
                    <!-- Left Column: Contact Info -->
                    <BaseCard class="h-full">
                        <div class="h-full flex flex-col justify-center space-y-6">
                            <!-- Email -->
                            <div v-if="contactEmail" class="flex items-start gap-4">
                                <div
                                    class="flex-shrink-0 rounded-lg bg-primary-100 dark:bg-primary-900/40 p-3 text-primary-600 dark:text-primary-400"
                                >
                                    <Mail class="h-6 w-6" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-base-primary">
                                        {{ t('about.contact.email') }}
                                    </h3>
                                    <a
                                        :href="'mailto:' + contactEmail"
                                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 underline"
                                    >
                                        {{ contactEmail }}
                                    </a>
                                </div>
                            </div>

                            <!-- Phone -->
                            <div v-if="contactPhone" class="flex items-start gap-4">
                                <div
                                    class="flex-shrink-0 rounded-lg bg-primary-100 dark:bg-primary-900/40 p-3 text-primary-600 dark:text-primary-400"
                                >
                                    <Phone class="h-6 w-6" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-base-primary">
                                        {{ t('about.contact.phone') }}
                                    </h3>
                                    <a
                                        :href="'tel:' + contactPhone"
                                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 underline"
                                    >
                                        {{ contactPhone }}
                                    </a>
                                </div>
                            </div>

                            <!-- Address -->
                            <div v-if="contactAddress" class="flex items-start gap-4">
                                <div
                                    class="flex-shrink-0 rounded-lg bg-primary-100 dark:bg-primary-900/40 p-3 text-primary-600 dark:text-primary-400"
                                >
                                    <MapPin class="h-6 w-6" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-base-primary">
                                        {{ t('about.contact.address') }}
                                    </h3>
                                    <p class="text-base-secondary">
                                        {{ contactAddress }}
                                    </p>
                                </div>
                            </div>

                            <!-- Social Media Links -->
                            <div v-if="hasSocialLinks" class="pt-4 border-t border-default">
                                <h3 class="font-semibold text-base-primary mb-4">
                                    {{ t('about.contact.social.title') }}
                                </h3>
                                <div class="flex gap-4">
                                    <a
                                        v-if="socialLinks.facebook"
                                        :href="socialLinks.facebook"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-lg bg-neutral-100 dark:bg-neutral-700 p-3 text-neutral-600 dark:text-neutral-400 hover:bg-primary-100 dark:hover:bg-primary-900/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                        aria-label="Facebook"
                                    >
                                        <svg
                                            class="h-6 w-6"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"
                                            />
                                        </svg>
                                    </a>
                                    <a
                                        v-if="socialLinks.instagram"
                                        :href="socialLinks.instagram"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-lg bg-neutral-100 dark:bg-neutral-700 p-3 text-neutral-600 dark:text-neutral-400 hover:bg-primary-100 dark:hover:bg-primary-900/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                        aria-label="Instagram"
                                    >
                                        <svg
                                            class="h-6 w-6"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"
                                            />
                                        </svg>
                                    </a>
                                    <a
                                        v-if="socialLinks.twitter"
                                        :href="socialLinks.twitter"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-lg bg-neutral-100 dark:bg-neutral-700 p-3 text-neutral-600 dark:text-neutral-400 hover:bg-primary-100 dark:hover:bg-primary-900/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                        aria-label="X (Twitter)"
                                    >
                                        <svg
                                            class="h-6 w-6"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"
                                            />
                                        </svg>
                                    </a>
                                    <a
                                        v-if="socialLinks.discord"
                                        :href="socialLinks.discord"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-lg bg-neutral-100 dark:bg-neutral-700 p-3 text-neutral-600 dark:text-neutral-400 hover:bg-primary-100 dark:hover:bg-primary-900/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                        aria-label="Discord"
                                    >
                                        <svg
                                            class="h-6 w-6"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189z"
                                            />
                                        </svg>
                                    </a>
                                    <a
                                        v-if="socialLinks.tiktok"
                                        :href="socialLinks.tiktok"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-lg bg-neutral-100 dark:bg-neutral-700 p-3 text-neutral-600 dark:text-neutral-400 hover:bg-primary-100 dark:hover:bg-primary-900/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                        aria-label="TikTok"
                                    >
                                        <svg
                                            class="h-6 w-6"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 20.1a6.34 6.34 0 0010.86-4.43v-7a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-1-.1z"
                                            />
                                        </svg>
                                    </a>
                                    <a
                                        v-if="socialLinks.bluesky"
                                        :href="socialLinks.bluesky"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-lg bg-neutral-100 dark:bg-neutral-700 p-3 text-neutral-600 dark:text-neutral-400 hover:bg-primary-100 dark:hover:bg-primary-900/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                        aria-label="Bluesky"
                                    >
                                        <svg
                                            class="h-6 w-6"
                                            fill="currentColor"
                                            viewBox="0 0 600 530"
                                        >
                                            <path
                                                d="m135.72 44.03c66.496 49.921 138.02 151.14 164.28 205.46 26.262-54.316 97.782-155.54 164.28-205.46 47.98-36.021 125.72-63.892 125.72 24.795 0 17.712-10.155 148.79-16.111 170.07-20.703 73.984-96.144 92.854-163.25 81.433 117.3 19.964 147.14 86.092 82.697 152.22-122.39 125.59-175.91-31.511-189.63-71.766-2.514-7.3797-3.6904-10.832-3.7077-7.8964-0.0174-2.9357-1.1937 0.51669-3.7077 7.8964-13.714 40.255-67.233 197.36-189.63 71.766-64.444-66.128-34.605-132.26 82.697-152.22-67.108 11.421-142.55-7.4491-163.25-81.433-5.9562-21.282-16.111-152.36-16.111-170.07 0-88.687 77.742-60.816 125.72-24.795z"
                                            />
                                        </svg>
                                    </a>
                                    <a
                                        v-if="socialLinks.telegram"
                                        :href="socialLinks.telegram"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-lg bg-neutral-100 dark:bg-neutral-700 p-3 text-neutral-600 dark:text-neutral-400 hover:bg-primary-100 dark:hover:bg-primary-900/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                        aria-label="Telegram"
                                    >
                                        <svg
                                            class="h-6 w-6"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"
                                            />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </BaseCard>

                    <!-- Right Column: Contact Form -->
                    <ContactForm />
                </div>
            </section>

            <!-- Location Section -->
            <BaseCard :title="t('about.location.title')">
                <div
                    class="h-64 md:h-80 rounded-lg overflow-hidden"
                    :aria-label="t('about.location.title')"
                >
                    <Suspense>
                        <LocationMap :location="location" />
                        <template #fallback>
                            <div
                                class="h-full flex items-center justify-center bg-neutral-100 dark:bg-neutral-800"
                            >
                                <LoadingSpinner />
                            </div>
                        </template>
                    </Suspense>
                </div>
                <p class="mt-4 text-sm text-base-secondary">
                    <a
                        :href="openStreetMapUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        {{ t('about.location.viewOnMap') }} →
                    </a>
                </p>
            </BaseCard>
        </main>
    </DefaultLayout>
</template>
