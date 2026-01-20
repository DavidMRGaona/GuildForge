<script setup lang="ts">
import { defineAsyncComponent, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import ContactForm from '@/components/contact/ContactForm.vue';
import { useSeo } from '@/composables/useSeo';
import { useMap } from '@/composables/useMap';
import { buildFullScreenHeroImageUrl } from '@/utils/cloudinary';
import type { Activity, ActivityIcon, JoinStep, SocialMediaLinks } from '@/types/models';

interface Props {
    associationName: string;
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
}

const props = defineProps<Props>();

const LocationMap = defineAsyncComponent(
    () => import('@/components/map/LocationMap.vue')
);

const { t } = useI18n();

useSeo({
    title: t('about.title', { appName: props.associationName }),
    description: props.aboutTagline || t('about.subtitle'),
});

const { location, getOpenStreetMapUrl } = useMap({ autoLoad: true });

const openStreetMapUrl = computed(() => {
    if (!location.value) return 'https://www.openstreetmap.org';
    return getOpenStreetMapUrl();
});

const hasJoinSteps = computed(() => props.joinSteps.length > 0);

const socialLinks = computed<SocialMediaLinks>(() => ({
    facebook: props.socialFacebook || undefined,
    instagram: props.socialInstagram || undefined,
    twitter: props.socialTwitter || undefined,
    discord: props.socialDiscord || undefined,
    tiktok: props.socialTiktok || undefined,
}));

const hasSocialLinks = computed(() =>
    props.socialFacebook || props.socialInstagram || props.socialTwitter || props.socialDiscord || props.socialTiktok
);

const heroImageUrl = computed(() => buildFullScreenHeroImageUrl(props.aboutHeroImage));

const scrollToContact = () => {
    const contactSection = document.getElementById('contact-section');
    if (contactSection) {
        contactSection.scrollIntoView({ behavior: 'smooth' });
    } else if (props.contactEmail) {
        window.location.href = `mailto:${props.contactEmail}`;
    }
};

const activityIconPaths: Record<ActivityIcon, string> = {
    dice: 'M6.5 9a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm5 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm5-6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm-5-3a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM4.5 2A2.5 2.5 0 0 0 2 4.5v15A2.5 2.5 0 0 0 4.5 22h15a2.5 2.5 0 0 0 2.5-2.5v-15A2.5 2.5 0 0 0 19.5 2h-15ZM4 4.5a.5.5 0 0 1 .5-.5h15a.5.5 0 0 1 .5.5v15a.5.5 0 0 1-.5.5h-15a.5.5 0 0 1-.5-.5v-15Z',
    sword: 'M14.586 2a2 2 0 0 1 1.284.47l.13.116L21.414 8a2 2 0 0 1 .117 2.7l-.117.128-4.243 4.243 1.415 1.415a1 1 0 0 1 .083 1.32l-.083.094-2.828 2.828a1 1 0 0 1-1.32.083l-.094-.083-1.415-1.415-4.242 4.243a2 2 0 0 1-2.7.117l-.128-.117L.586 18.164a2 2 0 0 1-.117-2.7l.117-.128L14.586 1.336a2 2 0 0 1 1.29-.47l.124-.003L14.586 2ZM3 14.414l6.586 6.586 3.828-3.829-6.585-6.585L3 14.414Zm12-10.828-3.414 3.414 6.585 6.586L21.586 10 15 3.414l-3.414.172Z',
    book: 'M12 6.042A8.967 8.967 0 0 0 6 4a8.967 8.967 0 0 0-6 2.042V20a8.967 8.967 0 0 1 6-2.042 8.967 8.967 0 0 1 6 2.042 8.967 8.967 0 0 1 6-2.042 8.967 8.967 0 0 1 6 2.042V6.042A8.967 8.967 0 0 0 18 4a8.967 8.967 0 0 0-6 2.042ZM12 18a10.968 10.968 0 0 0-5-1.5V6.75A6.967 6.967 0 0 1 12 5a6.967 6.967 0 0 1 5 1.75V16.5A10.968 10.968 0 0 0 12 18Z',
    users: 'M15 6a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm-3 3a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM6 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm-3 3a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm12 5c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4Zm-6 5v-1c0-.64 2.6-2 6-2s6 1.36 6 2v1H9ZM6 17c-2.67 0-8 1.34-8 4v3h6v-2H2v-1c0-.64 2.6-2 6-2 .68 0 1.36.07 2 .18v-2.07c-.66-.07-1.34-.11-2-.11Z',
    calendar: 'M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2Zm0 16H5V9h14v10ZM5 7V5h14v2H5Zm2 4h5v5H7v-5Z',
    map: 'M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5a.5.5 0 0 0 .5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5a.5.5 0 0 0-.5-.5ZM15 19l-6-2.11V5l6 2.11V19Z',
    trophy: 'M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94.63 1.5 1.98 2.63 3.61 2.96V19H7v2h10v-2h-4v-3.1c1.63-.33 2.98-1.46 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2Zm-2 3c0 2.21-1.79 4-4 4s-4-1.79-4-4V5h8v3ZM5 8V7h2v1.82C5.84 8.4 5 7.3 5 8Zm14 0c0-.7-.84.4-2 .82V7h2v1Z',
    puzzle: 'M20.5 11H19V7a2 2 0 0 0-2-2h-4V3.5a2.5 2.5 0 0 0-5 0V5H4a2 2 0 0 0-2 2v3.8h1.5a1.5 1.5 0 0 1 0 3H2V17a2 2 0 0 0 2 2h3.8v-1.5a1.5 1.5 0 0 1 3 0V19H17a2 2 0 0 0 2-2v-4h1.5a2.5 2.5 0 0 0 0-5Z',
    sparkles: 'M12 3l1.59 5.26L19 10l-5.41 1.74L12 17l-1.59-5.26L5 10l5.41-1.74L12 3Zm5 12l.79 2.63L20.5 19l-2.71.87L17 22.5l-.79-2.63L13.5 19l2.71-.87L17 15ZM6.5 13l.79 2.63L10 16.5l-2.71.87-.79 2.63-.79-2.63L3 16.5l2.71-.87L6.5 13Z',
    heart: 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35Z',
};
</script>

<template>
    <DefaultLayout>
        <!-- Hero Section -->
        <div
            class="relative h-64 md:h-80 flex items-center justify-center overflow-hidden"
            :class="{ 'bg-gradient-to-r from-amber-500 to-amber-600': !heroImageUrl }"
        >
            <!-- Background Image -->
            <img
                v-if="heroImageUrl"
                :src="heroImageUrl"
                :alt="associationName"
                class="absolute inset-0 w-full h-full object-cover"
            />
            <!-- Dark Overlay -->
            <div
                class="absolute inset-0 bg-black/40"
                :class="{ 'bg-black/50': heroImageUrl }"
            ></div>
            <!-- Content -->
            <div class="relative z-10 text-center px-4">
                <h1
                    class="text-3xl md:text-4xl lg:text-5xl font-bold text-white drop-shadow-lg"
                >
                    {{ t('about.title', { appName: associationName }) }}
                </h1>
                <p
                    v-if="aboutTagline"
                    class="mt-3 text-lg md:text-xl text-white/90 drop-shadow"
                >
                    {{ aboutTagline }}
                </p>
            </div>
        </div>

        <!-- Main Content -->
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Activities Section -->
            <section v-if="activities.length > 0" class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    {{ t('about.whatWeDo.title') }}
                </h2>
                <div class="flex flex-wrap justify-center gap-6">
                    <div
                        v-for="(activity, index) in activities"
                        :key="index"
                        class="w-full sm:w-[calc(50%-0.75rem)] lg:w-[calc(33.333%-1rem)] xl:w-[calc(25%-1.125rem)] bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                    >
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 rounded-lg bg-amber-100 p-3 text-amber-600">
                                <svg
                                    class="h-6 w-6"
                                    fill="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path :d="activityIconPaths[activity.icon]" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 mb-1">
                                    {{ activity.title }}
                                </h3>
                                <p class="text-sm text-gray-600 leading-relaxed">
                                    {{ activity.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- History Section -->
            <BaseCard
                v-if="aboutHistory"
                :title="t('about.history.title')"
                class="mb-8"
            >
                <div
                    v-html="aboutHistory"
                    class="prose prose-gray max-w-none"
                ></div>
            </BaseCard>

            <!-- Join Section -->
            <section v-if="hasJoinSteps" class="space-y-12 mb-8">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $t('about.join.title') }}
                    </h2>
                </div>

                <!-- Desktop: Horizontal Timeline -->
                <div class="hidden md:block">
                    <div class="relative">
                        <!-- Connection line -->
                        <div class="absolute left-0 right-0 top-6 h-0.5 bg-amber-200 dark:bg-amber-900" />

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
                                    class="relative z-10 flex h-12 w-12 items-center justify-center rounded-full bg-amber-500 text-xl font-bold text-white shadow-lg"
                                >
                                    {{ index + 1 }}
                                </div>

                                <!-- Content -->
                                <div class="mt-4 max-w-[200px]">
                                    <h3 class="font-semibold text-gray-900 dark:text-white">
                                        {{ step.title }}
                                    </h3>
                                    <p
                                        v-if="step.description"
                                        class="mt-1 text-sm text-gray-600 dark:text-gray-400"
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
                        <div class="absolute bottom-0 left-0 top-0 w-0.5 bg-amber-200 dark:bg-amber-900" />

                        <!-- Steps -->
                        <div class="space-y-8">
                            <div
                                v-for="(step, index) in joinSteps"
                                :key="index"
                                class="relative flex items-start gap-4"
                            >
                                <!-- Number circle -->
                                <div
                                    class="relative z-10 -ml-6 flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-amber-500 text-xl font-bold text-white shadow-lg"
                                >
                                    {{ index + 1 }}
                                </div>

                                <!-- Content -->
                                <div class="pt-2">
                                    <h3 class="font-semibold text-gray-900 dark:text-white">
                                        {{ step.title }}
                                    </h3>
                                    <p
                                        v-if="step.description"
                                        class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        {{ step.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CTA Button -->
                <div class="text-center">
                    <BaseButton variant="primary" size="lg" @click="scrollToContact">
                        {{ $t('about.join.contactUs') }}
                    </BaseButton>
                </div>
            </section>

            <!-- Contact Section - Two Column Layout -->
            <section id="contact-section" class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    {{ t('about.contact.title') }}
                </h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-stretch">
                    <!-- Left Column: Contact Info -->
                    <BaseCard class="h-full">
                        <div class="h-full flex flex-col justify-center space-y-6">
                            <!-- Email -->
                            <div v-if="contactEmail" class="flex items-start gap-4">
                                <div class="flex-shrink-0 rounded-lg bg-amber-100 p-3 text-amber-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">{{ t('about.contact.email') }}</h3>
                                    <a :href="'mailto:' + contactEmail" class="text-amber-600 hover:text-amber-500 underline">
                                        {{ contactEmail }}
                                    </a>
                                </div>
                            </div>

                            <!-- Phone -->
                            <div v-if="contactPhone" class="flex items-start gap-4">
                                <div class="flex-shrink-0 rounded-lg bg-amber-100 p-3 text-amber-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">{{ t('about.contact.phone') }}</h3>
                                    <a :href="'tel:' + contactPhone" class="text-amber-600 hover:text-amber-500 underline">
                                        {{ contactPhone }}
                                    </a>
                                </div>
                            </div>

                            <!-- Address -->
                            <div v-if="contactAddress" class="flex items-start gap-4">
                                <div class="flex-shrink-0 rounded-lg bg-amber-100 p-3 text-amber-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">{{ t('about.contact.address') }}</h3>
                                    <p class="text-gray-600">{{ contactAddress }}</p>
                                </div>
                            </div>

                            <!-- Social Media Links -->
                            <div v-if="hasSocialLinks" class="pt-4 border-t border-gray-200">
                                <h3 class="font-semibold text-gray-900 mb-4">{{ t('about.contact.social.title') }}</h3>
                                <div class="flex gap-4">
                                    <a v-if="socialLinks.facebook" :href="socialLinks.facebook" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-gray-100 p-3 text-gray-600 hover:bg-amber-100 hover:text-amber-600 transition-colors" aria-label="Facebook">
                                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                    </a>
                                    <a v-if="socialLinks.instagram" :href="socialLinks.instagram" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-gray-100 p-3 text-gray-600 hover:bg-amber-100 hover:text-amber-600 transition-colors" aria-label="Instagram">
                                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                    </a>
                                    <a v-if="socialLinks.twitter" :href="socialLinks.twitter" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-gray-100 p-3 text-gray-600 hover:bg-amber-100 hover:text-amber-600 transition-colors" aria-label="X (Twitter)">
                                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                    </a>
                                    <a v-if="socialLinks.discord" :href="socialLinks.discord" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-gray-100 p-3 text-gray-600 hover:bg-amber-100 hover:text-amber-600 transition-colors" aria-label="Discord">
                                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189z"/></svg>
                                    </a>
                                    <a v-if="socialLinks.tiktok" :href="socialLinks.tiktok" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-gray-100 p-3 text-gray-600 hover:bg-amber-100 hover:text-amber-600 transition-colors" aria-label="TikTok">
                                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 20.1a6.34 6.34 0 0010.86-4.43v-7a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-1-.1z"/></svg>
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
                        <LocationMap />
                        <template #fallback>
                            <div
                                class="h-full flex items-center justify-center bg-gray-100"
                            >
                                <LoadingSpinner />
                            </div>
                        </template>
                    </Suspense>
                </div>
                <p class="mt-4 text-sm text-gray-600">
                    <a
                        :href="openStreetMapUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-indigo-600 hover:text-indigo-500"
                    >
                        {{ t('about.location.viewOnMap') }} →
                    </a>
                </p>
            </BaseCard>
        </main>
    </DefaultLayout>
</template>
