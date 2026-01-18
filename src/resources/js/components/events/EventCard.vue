<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Event } from '@/types/models';
import { useEvents } from '@/composables/useEvents';
import BaseCard from '@/components/ui/BaseCard.vue';
import { buildCardImageUrl } from '@/utils/cloudinary';

interface Props {
    event: Event;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { formatDateRange, isUpcoming, getExcerpt } = useEvents();

const eventImageUrl = computed(() => buildCardImageUrl(props.event.imagePublicId));
</script>

<template>
    <Link
        :href="`/eventos/${props.event.slug}`"
        :aria-label="t('a11y.viewEvent', { title: props.event.title })"
        class="block transition-transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
    >
        <BaseCard :padding="false">
            <template #header>
                <div class="relative">
                    <img
                        v-if="eventImageUrl"
                        :src="eventImageUrl"
                        :alt="props.event.title"
                        loading="lazy"
                        class="aspect-video h-48 w-full object-cover"
                    />
                    <div
                        v-else
                        class="flex aspect-video h-48 w-full items-center justify-center bg-gradient-to-br from-amber-400 to-amber-600"
                    >
                        <svg
                            class="h-16 w-16 text-white/50"
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
                    <span
                        v-if="isUpcoming(props.event)"
                        class="absolute left-3 top-3 rounded-full bg-green-600 px-2 py-1 text-xs font-medium text-white"
                    >
                        {{ t('events.upcoming') }}
                    </span>
                </div>
            </template>

            <div class="p-4">
                <h3 class="mb-2 line-clamp-2 text-lg font-semibold text-gray-900">
                    {{ props.event.title }}
                </h3>

                <p class="mb-2 text-sm text-amber-600">
                    <span class="sr-only">{{ t('events.date') }}:</span>
                    {{ formatDateRange(props.event.startDate, props.event.endDate) }}
                </p>

                <p v-if="props.event.location" class="mb-3 text-sm text-gray-500">
                    <span class="sr-only">{{ t('events.location') }}:</span>
                    <svg
                        class="mr-1 inline-block h-4 w-4"
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
                    {{ props.event.location }}
                </p>

                <p class="line-clamp-3 text-sm text-gray-600">
                    {{ getExcerpt(props.event.description) }}
                </p>
            </div>
        </BaseCard>
    </Link>
</template>
