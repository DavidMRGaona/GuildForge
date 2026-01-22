<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Event } from '@/types/models';
import { useEvents } from '@/composables/useEvents';
import { useEventDateBadge } from '@/composables/useEventDateBadge';
import { useTags } from '@/composables/useTags';
import BaseCard from '@/components/ui/BaseCard.vue';
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

const eventImageUrl = computed(() => buildCardImageUrl(props.event.imagePublicId));
const dateBadge = computed(() => getDateBadge(props.event.startDate));

const { categoryTag, additionalTags } = useTags(computed(() => props.event.tags));

const isCompact = computed(() => props.variant === 'compact');

const imageHeight = computed(() => (isCompact.value ? 'h-40' : 'h-48'));
const iconSize = computed(() => (isCompact.value ? 'h-12 w-12' : 'h-16 w-16'));
const descriptionLines = computed(() => (isCompact.value ? 'line-clamp-2' : 'line-clamp-3'));
const excerptLength = computed(() => (isCompact.value ? 100 : 150));
</script>

<template>
    <Link
        :href="`/eventos/${props.event.slug}`"
        :aria-label="t('a11y.viewEvent', { title: props.event.title })"
        :class="[
            'block transition-all duration-200 hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-stone-900',
            isCompact
                ? 'group overflow-hidden rounded-lg border border-stone-200 bg-white shadow-sm hover:shadow-lg dark:border-stone-700 dark:bg-stone-800 dark:shadow-stone-900/50'
                : 'transition-transform',
        ]"
    >
        <!-- Compact variant: inline card -->
        <template v-if="isCompact">
            <!-- Image with date badge and category overlay -->
            <div class="relative">
                <img
                    v-if="eventImageUrl"
                    :src="eventImageUrl"
                    :alt="props.event.title"
                    loading="lazy"
                    :class="['aspect-video w-full object-cover', imageHeight]"
                />
                <ImagePlaceholder
                    v-else
                    variant="event"
                    :height="imageHeight"
                    :icon-size="iconSize"
                />

                <!-- Date badge overlay (compact only) -->
                <div
                    class="absolute left-3 top-3 flex flex-col items-center rounded bg-white px-2 py-1 shadow-md dark:bg-stone-800"
                >
                    <span
                        class="text-xl font-bold leading-none text-amber-600 dark:text-amber-500"
                        >{{ dateBadge.day }}</span
                    >
                    <span
                        class="text-xs uppercase tracking-wide text-stone-600 dark:text-stone-400"
                        >{{ dateBadge.month }}</span
                    >
                </div>

                <!-- Category badge overlay -->
                <TagBadge
                    v-if="categoryTag"
                    :tag="categoryTag"
                    :linkable="false"
                    variant="category"
                    content-type="events"
                    class="absolute right-3 top-3"
                />
            </div>

            <!-- Content -->
            <div class="p-4">
                <h3
                    class="mb-2 line-clamp-2 text-lg font-semibold text-stone-900 group-hover:text-amber-600 dark:text-stone-100 dark:group-hover:text-amber-500"
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

                <p
                    v-if="props.event.location"
                    class="mb-2 flex items-center text-sm text-stone-500 dark:text-stone-400"
                >
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

                <p :class="['text-sm text-stone-600 dark:text-stone-300', descriptionLines]">
                    {{ getExcerpt(props.event.description, excerptLength) }}
                </p>
            </div>
        </template>

        <!-- Default variant: BaseCard wrapper -->
        <BaseCard v-else :padding="false">
            <template #header>
                <div class="relative">
                    <img
                        v-if="eventImageUrl"
                        :src="eventImageUrl"
                        :alt="props.event.title"
                        loading="lazy"
                        :class="['aspect-video w-full object-cover', imageHeight]"
                    />
                    <ImagePlaceholder
                        v-else
                        variant="event"
                        :height="imageHeight"
                        :icon-size="iconSize"
                    />

                    <!-- Category badge as overlay on image -->
                    <TagBadge
                        v-if="categoryTag"
                        :tag="categoryTag"
                        :linkable="false"
                        variant="category"
                        content-type="events"
                        class="absolute left-3 top-3"
                    />

                    <span
                        v-if="isUpcoming(props.event)"
                        class="absolute right-3 top-3 rounded-full bg-green-600 px-2 py-1 text-xs font-medium text-white dark:bg-green-500"
                    >
                        {{ t('events.upcoming') }}
                    </span>
                </div>
            </template>

            <div class="p-4">
                <h3
                    class="mb-2 line-clamp-2 text-lg font-semibold text-stone-900 dark:text-stone-100"
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

                <p class="mb-2 text-sm text-amber-600 dark:text-amber-500">
                    <span class="sr-only">{{ t('events.date') }}:</span>
                    {{ formatDateRange(props.event.startDate, props.event.endDate) }}
                </p>

                <p
                    v-if="props.event.location"
                    class="mb-3 text-sm text-stone-500 dark:text-stone-400"
                >
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

                <p :class="['text-sm text-stone-600 dark:text-stone-300', descriptionLines]">
                    {{ getExcerpt(props.event.description, excerptLength) }}
                </p>
            </div>
        </BaseCard>
    </Link>
</template>
