<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Event } from '@/types/models';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import TagBadge from '@/components/ui/TagBadge.vue';
import TagList from '@/components/ui/TagList.vue';
import { useEvents } from '@/composables/useEvents';
import { useTags } from '@/composables/useTags';
import { useSeo } from '@/composables/useSeo';
import { useRoutes } from '@/composables/useRoutes';
import { buildHeroImageUrl } from '@/utils/cloudinary';
import ModuleSlot from '@/components/layout/ModuleSlot.vue';

interface Props {
    event: Event;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { formatDateRange, formatPrice, isUpcoming } = useEvents();
const routes = useRoutes();

const heroImageUrl = computed(() => buildHeroImageUrl(props.event.imagePublicId));

const { categoryTag, additionalTags, hasTags } = useTags(computed(() => props.event.tags));

useSeo({
    title: props.event.title,
    description: props.event.description,
    image: heroImageUrl.value,
    type: 'article',
});
</script>

<template>
    <DefaultLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <Link
                    :href="routes.events.index"
                    class="inline-flex items-center text-sm text-base-muted hover:text-base-primary focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-page"
                >
                    <svg
                        class="mr-1 h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                    {{ t('common.back') }}
                </Link>
            </div>

            <article
                class="overflow-hidden rounded-lg bg-surface shadow dark:shadow-neutral-900/50"
            >
                <img
                    v-if="heroImageUrl"
                    :src="heroImageUrl"
                    :alt="props.event.title"
                    class="h-48 w-full object-cover sm:h-64 md:h-80 lg:h-96"
                />
                <div
                    v-else
                    class="flex h-48 w-full items-center justify-center bg-gradient-to-br from-primary-400 to-primary-600 sm:h-64 md:h-80 lg:h-96"
                >
                    <svg
                        class="h-24 w-24 text-white/50"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                </div>

                <div class="p-6 sm:p-8">
                    <div class="mb-4 flex items-center gap-2">
                        <span
                            v-if="isUpcoming(props.event)"
                            class="rounded-full bg-success-light px-3 py-1 text-sm font-medium text-success"
                        >
                            {{ t('events.upcoming') }}
                        </span>
                        <span
                            v-else
                            class="rounded-full bg-neutral-100 px-3 py-1 text-sm font-medium text-neutral-800 dark:bg-neutral-700 dark:text-neutral-300"
                        >
                            {{ t('events.past') }}
                        </span>
                    </div>

                    <h1 class="mb-4 text-3xl font-bold text-base-primary sm:text-4xl">
                        {{ props.event.title }}
                    </h1>

                    <!-- Tags -->
                    <div v-if="hasTags" class="mb-4 flex flex-wrap items-center gap-2">
                        <TagBadge
                            v-if="categoryTag"
                            :tag="categoryTag"
                            variant="category"
                            content-type="events"
                        />
                        <TagList
                            v-if="additionalTags.length"
                            :tags="additionalTags"
                            content-type="events"
                        />
                    </div>

                    <div class="mb-6 flex flex-col gap-3 text-base-secondary sm:flex-row sm:gap-6">
                        <div class="flex items-center">
                            <span class="sr-only">{{ t('events.date') }}:</span>
                            <svg
                                class="mr-2 h-5 w-5 text-primary-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                            <span>{{
                                formatDateRange(props.event.startDate, props.event.endDate)
                            }}</span>
                        </div>

                        <div v-if="props.event.location" class="flex items-center">
                            <span class="sr-only">{{ t('events.location') }}:</span>
                            <svg
                                class="mr-2 h-5 w-5 text-primary-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                            <span>{{ props.event.location }}</span>
                        </div>
                    </div>

                    <div
                        v-if="props.event.memberPrice !== null"
                        class="mb-6 rounded-lg bg-primary-50 p-4 dark:bg-primary-900/20"
                    >
                        <h3 class="mb-2 font-semibold text-base-primary">
                            {{ t('events.pricing') }}
                        </h3>
                        <div class="flex flex-col gap-1 sm:flex-row sm:gap-6">
                            <p class="text-base-secondary">
                                <span class="font-medium">{{ t('events.memberPrice') }}:</span>
                                {{ formatPrice(props.event.memberPrice) }}
                            </p>
                            <p
                                v-if="props.event.nonMemberPrice !== null"
                                class="text-base-secondary"
                            >
                                <span class="font-medium">{{ t('events.nonMemberPrice') }}:</span>
                                {{ formatPrice(props.event.nonMemberPrice) }}
                            </p>
                        </div>
                    </div>
                    <div v-else class="mb-6 rounded-lg bg-success-light p-4">
                        <p class="font-medium text-success">
                            {{ t('events.free') }}
                        </p>
                    </div>

                    <!-- Module slot for event actions (e.g., registration button) -->
                    <div class="mb-6">
                        <ModuleSlot name="event-detail-actions" />
                    </div>

                    <div class="prose max-w-none">
                        <p class="whitespace-pre-line">
                            {{ props.event.description }}
                        </p>
                    </div>

                    <div class="mt-8 border-t border-default pt-6">
                        <Link :href="routes.events.index">
                            <BaseButton variant="primary">
                                {{ t('common.viewAll') }} {{ t('common.events').toLowerCase() }}
                            </BaseButton>
                        </Link>
                    </div>
                </div>
            </article>
        </div>
    </DefaultLayout>
</template>
