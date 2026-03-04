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
import SocialLinks from '@/components/ui/SocialLinks.vue';
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
                                <SocialLinks :links="socialLinks" size="md" variant="light" />
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
