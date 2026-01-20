<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Event } from '@/types/models';
import { useEvents } from '@/composables/useEvents';
import { useEventDateBadge } from '@/composables/useEventDateBadge';
import ImagePlaceholder from '@/components/ui/ImagePlaceholder.vue';
import { buildCardImageUrl } from '@/utils/cloudinary';

interface Props {
    event: Event;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { getExcerpt } = useEvents();
const { getDateBadge } = useEventDateBadge();

const eventImageUrl = computed(() => buildCardImageUrl(props.event.imagePublicId));
const dateBadge = computed(() => getDateBadge(props.event.startDate));
</script>

<template>
    <Link
        :href="`/eventos/${props.event.slug}`"
        :aria-label="t('a11y.viewEvent', { title: props.event.title })"
        class="group block overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition-all duration-200 hover:scale-[1.02] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
    >
        <!-- Image with date badge overlay -->
        <div class="relative">
            <img
                v-if="eventImageUrl"
                :src="eventImageUrl"
                :alt="props.event.title"
                loading="lazy"
                class="aspect-video h-40 w-full object-cover"
            />
            <ImagePlaceholder v-else variant="event" height="h-40" icon-size="h-12 w-12" />

            <!-- Date badge overlay -->
            <div class="absolute left-3 top-3 flex flex-col items-center rounded bg-white px-2 py-1 shadow-md">
                <span class="text-xl font-bold leading-none text-amber-600">{{ dateBadge.day }}</span>
                <span class="text-xs uppercase tracking-wide text-slate-600">{{ dateBadge.month }}</span>
            </div>
        </div>

        <!-- Content -->
        <div class="p-4">
            <h3 class="mb-2 line-clamp-2 text-lg font-semibold text-gray-900 group-hover:text-amber-600">
                {{ props.event.title }}
            </h3>

            <p v-if="props.event.location" class="mb-2 flex items-center text-sm text-gray-500">
                <svg
                    class="mr-1.5 h-4 w-4 flex-shrink-0"
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
                <span class="sr-only">{{ t('events.location') }}:</span>
                <span class="truncate">{{ props.event.location }}</span>
            </p>

            <p class="line-clamp-2 text-sm text-gray-600">
                {{ getExcerpt(props.event.description, 100) }}
            </p>
        </div>
    </Link>
</template>
