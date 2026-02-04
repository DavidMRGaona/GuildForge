<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Event } from '@/types/models';
import { useEvents } from '@/composables/useEvents';
import { useEventDateBadge } from '@/composables/useEventDateBadge';
import { useTags } from '@/composables/useTags';
import { useRoutes } from '@/composables/useRoutes';
import ImagePlaceholder from '@/components/ui/ImagePlaceholder.vue';
import TagBadge from '@/components/ui/TagBadge.vue';
import TagList from '@/components/ui/TagList.vue';
import { buildCardImageUrl } from '@/utils/cloudinary';

interface Props {
    event: Event;
    variant?: 'default' | 'compact';
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
});

const { t } = useI18n();
const { formatDateRange, isUpcoming, getExcerpt } = useEvents();
const { getDateBadge } = useEventDateBadge();
const routes = useRoutes();

const eventImageUrl = computed(() => buildCardImageUrl(props.event.imagePublicId));
const dateBadge = computed(() => getDateBadge(props.event.startDate));

const { categoryTag, additionalTags } = useTags(computed(() => props.event.tags));

const isCompact = computed(() => props.variant === 'compact');

const imageHeight = computed(() => (isCompact.value ? 'h-40' : 'h-40'));
const iconSize = computed(() => (isCompact.value ? 'h-12 w-12' : 'h-12 w-12'));
const descriptionLines = computed(() => (isCompact.value ? 'line-clamp-2' : 'line-clamp-2'));
const excerptLength = computed(() => (isCompact.value ? 100 : 120));
</script>

<template>
    <Link
        :href="routes.events.show(props.event.slug)"
        :aria-label="t('a11y.viewEvent', { title: props.event.title })"
        class="group block overflow-hidden rounded-lg bg-surface shadow-sm transition-all duration-200 hover:scale-[1.02] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:shadow-neutral-900/50 dark:focus:ring-offset-page"
    >
        <!-- Image with badges -->
        <div class="relative">
            <img
                v-if="eventImageUrl"
                :src="eventImageUrl"
                :alt="props.event.title"
                loading="lazy"
                :class="['aspect-video w-full object-cover', imageHeight]"
            />
            <ImagePlaceholder v-else variant="event" :height="imageHeight" :icon-size="iconSize" />

            <!-- Compact: Date badge overlay (left) -->
            <div
                v-if="isCompact"
                class="absolute left-3 top-3 flex flex-col items-center rounded bg-surface px-2 py-1 shadow-md"
            >
                <span class="text-xl font-bold leading-none text-primary-600 dark:text-primary-500">
                    {{ dateBadge.day }}
                </span>
                <span class="text-xs uppercase tracking-wide text-base-secondary">
                    {{ dateBadge.month }}
                </span>
            </div>

            <!-- Default: Category badge overlay (left) -->
            <TagBadge
                v-if="!isCompact && categoryTag"
                :tag="categoryTag"
                :linkable="false"
                variant="category"
                badge-style="overlay"
                content-type="events"
                class="absolute left-3 top-3"
            />

            <!-- Compact: Category badge overlay (right) -->
            <TagBadge
                v-if="isCompact && categoryTag"
                :tag="categoryTag"
                :linkable="false"
                variant="category"
                badge-style="overlay"
                content-type="events"
                class="absolute right-3 top-3"
            />

            <!-- Default: Upcoming badge (right) -->
            <span
                v-if="!isCompact && isUpcoming(props.event)"
                class="absolute right-3 top-3 rounded-full bg-success px-2 py-1 text-xs font-medium text-base-inverted"
            >
                {{ t('events.upcoming') }}
            </span>
        </div>

        <!-- Content -->
        <div class="p-4">
            <h3
                class="mb-2 line-clamp-2 text-lg font-semibold text-base-primary group-hover:text-primary-600 dark:group-hover:text-primary-500"
            >
                {{ props.event.title }}
            </h3>

            <TagList
                v-if="additionalTags.length"
                :tags="additionalTags"
                :linkable="false"
                content-type="events"
                class="mb-2"
            />

            <!-- Default: Date as text -->
            <p v-if="!isCompact" class="mb-2 text-sm text-primary-600 dark:text-primary-500">
                <span class="sr-only">{{ t('events.date') }}:</span>
                {{ formatDateRange(props.event.startDate, props.event.endDate) }}
            </p>

            <!-- Location -->
            <p v-if="props.event.location" class="mb-2 flex items-center text-sm text-base-muted">
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

            <p :class="['text-sm text-base-secondary', descriptionLines]">
                {{ getExcerpt(props.event.description, excerptLength) }}
            </p>
        </div>
    </Link>
</template>
