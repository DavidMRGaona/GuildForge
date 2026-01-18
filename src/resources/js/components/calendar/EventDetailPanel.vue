<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { CalendarEvent } from '@/types/models';
import { buildCardImageUrl } from '@/utils/cloudinary';
import { useEvents } from '@/composables/useEvents';
import BaseButton from '@/components/ui/BaseButton.vue';

interface Props {
    event: CalendarEvent | null;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { formatDateRange, formatPrice, getExcerpt } = useEvents();

const imageUrl = computed(() => buildCardImageUrl(props.event?.imagePublicId));

const formattedDate = computed(() => {
    if (!props.event) return '';
    return formatDateRange(props.event.start, props.event.end);
});

const priceDisplay = computed(() => {
    if (!props.event) return '';

    const memberPrice = props.event.memberPrice;
    const nonMemberPrice = props.event.nonMemberPrice;

    if (memberPrice === null && nonMemberPrice === null) {
        return t('calendar.free');
    }

    const parts: string[] = [];
    if (memberPrice !== null) {
        parts.push(`${t('events.memberPrice')}: ${formatPrice(memberPrice)}`);
    }
    if (nonMemberPrice !== null) {
        parts.push(`${t('events.nonMemberPrice')}: ${formatPrice(nonMemberPrice)}`);
    }

    return parts.join(' / ');
});

const truncatedDescription = computed(() => {
    if (!props.event?.description) return '';
    return getExcerpt(props.event.description, 200);
});
</script>

<template>
    <div class="flex h-full flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <!-- Empty state -->
        <div
            v-if="!event"
            class="flex flex-1 flex-col items-center justify-center text-center"
        >
            <svg
                class="mx-auto h-12 w-12 text-gray-300"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                />
            </svg>
            <p class="mt-3 text-sm text-gray-500">
                {{ t('calendar.selectEvent') }}
            </p>
        </div>

        <!-- Event details -->
        <template v-else>
            <!-- Event image -->
            <div v-if="imageUrl" class="mb-4">
                <img
                    :src="imageUrl"
                    :alt="event.title"
                    class="h-40 w-full rounded-lg object-cover"
                />
            </div>

            <!-- Event content -->
            <div class="flex-1">
                <!-- Title -->
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ event.title }}
                </h3>

                <!-- Date -->
                <div class="mt-2 flex items-center text-sm text-gray-600">
                    <svg
                        class="mr-2 h-4 w-4 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                    {{ formattedDate }}
                </div>

                <!-- Location -->
                <div v-if="event.location" class="mt-2 flex items-center text-sm text-gray-600">
                    <svg
                        class="mr-2 h-4 w-4 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
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
                    {{ event.location }}
                </div>

                <!-- Description -->
                <p v-if="truncatedDescription" class="mt-3 text-sm text-gray-600">
                    {{ truncatedDescription }}
                </p>

                <!-- Pricing -->
                <div v-if="priceDisplay" class="mt-3 flex items-center text-sm">
                    <svg
                        class="mr-2 h-4 w-4 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <span class="text-gray-600">{{ priceDisplay }}</span>
                </div>
            </div>

            <!-- Action button -->
            <div class="mt-4 pt-4 border-t border-gray-100">
                <Link :href="event.url" class="block">
                    <BaseButton variant="primary" size="sm" class="w-full">
                        {{ t('calendar.viewEvent') }}
                    </BaseButton>
                </Link>
            </div>
        </template>
    </div>
</template>
