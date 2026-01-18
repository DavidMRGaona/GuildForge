<script setup lang="ts">
import { defineAsyncComponent, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useSeo } from '@/composables/useSeo';
import { useMap } from '@/composables/useMap';

const LocationMap = defineAsyncComponent(
    () => import('@/components/map/LocationMap.vue')
);

const { t } = useI18n();

useSeo({
    title: t('about.title'),
    description: t('about.subtitle'),
});

const { location, getOpenStreetMapUrl } = useMap({ autoLoad: true });

const openStreetMapUrl = computed(() => {
    if (!location.value) return 'https://www.openstreetmap.org';
    return getOpenStreetMapUrl();
});
</script>

<template>
    <DefaultLayout>
        <!-- Page Header -->
        <div class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">
                    {{ t('about.title') }}
                </h1>
                <p class="mt-2 text-lg text-gray-600">
                    {{ t('about.subtitle') }}
                </p>
            </div>
        </div>

        <!-- Main Content -->
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- History Section -->
            <BaseCard :title="t('about.history.title')" class="mb-8">
                <p class="text-gray-700 leading-relaxed">
                    {{ t('about.history.content') }}
                </p>
            </BaseCard>

            <!-- What We Do Section -->
            <BaseCard :title="t('about.whatWeDo.title')" class="mb-8">
                <div class="flex items-start gap-4">
                    <div
                        class="flex-shrink-0 rounded-lg bg-indigo-100 p-3 text-indigo-600"
                    >
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                            />
                        </svg>
                    </div>
                    <p class="flex-1 text-gray-700 leading-relaxed">
                        {{ t('about.whatWeDo.activities.content') }}
                    </p>
                </div>
            </BaseCard>

            <!-- How to Join Section -->
            <BaseCard :title="t('about.join.title')" class="mb-8">
                <p class="mb-4 text-gray-700 leading-relaxed">
                    {{ t('about.join.content') }}
                </p>
                <ol class="space-y-3 pl-5 list-decimal text-gray-700">
                    <li>{{ t('about.join.step1') }}</li>
                    <li>{{ t('about.join.step2') }}</li>
                    <li>{{ t('about.join.step3') }}</li>
                </ol>
            </BaseCard>

            <!-- Contact Section -->
            <BaseCard :title="t('about.contact.title')" class="mb-8">
                <p class="text-gray-700 leading-relaxed">
                    {{ t('about.contact.placeholder') }}
                </p>
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
