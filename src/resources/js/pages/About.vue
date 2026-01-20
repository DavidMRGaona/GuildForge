<script setup lang="ts">
import { defineAsyncComponent, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useSeo } from '@/composables/useSeo';
import { useMap } from '@/composables/useMap';
import { buildFullScreenHeroImageUrl } from '@/utils/cloudinary';
import type { Activity, ActivityIcon } from '@/types/models';

interface Props {
    associationName: string;
    aboutHistory: string;
    contactEmail: string;
    contactPhone: string;
    contactAddress: string;
    aboutHeroImage: string;
    aboutTagline: string;
    activities: Activity[];
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

const hasContactInfo = computed(() =>
    props.contactEmail || props.contactPhone || props.contactAddress
);

const heroImageUrl = computed(() => buildFullScreenHeroImageUrl(props.aboutHeroImage));

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

            <!-- Contact Section -->
            <BaseCard
                v-if="hasContactInfo"
                :title="t('about.contact.title')"
                class="mb-8"
            >
                <div class="space-y-3">
                    <div v-if="contactEmail" class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2">
                        <span class="font-medium text-gray-700">{{ t('about.contact.email') }}:</span>
                        <a
                            :href="'mailto:' + contactEmail"
                            class="text-indigo-600 hover:text-indigo-500 underline"
                        >
                            {{ contactEmail }}
                        </a>
                    </div>
                    <div v-if="contactPhone" class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2">
                        <span class="font-medium text-gray-700">{{ t('about.contact.phone') }}:</span>
                        <a
                            :href="'tel:' + contactPhone"
                            class="text-indigo-600 hover:text-indigo-500 underline"
                        >
                            {{ contactPhone }}
                        </a>
                    </div>
                    <div v-if="contactAddress" class="flex flex-col gap-1">
                        <span class="font-medium text-gray-700">{{ t('about.contact.address') }}:</span>
                        <p class="text-gray-700 leading-relaxed">{{ contactAddress }}</p>
                    </div>
                </div>
            </BaseCard>

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
