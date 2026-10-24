<script setup lang="ts">
import { Calendar, MapPin, Ticket } from '@lucide/vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { CalendarEntry } from '@/types/models';
import { buildCardImageUrl } from '@/utils/cloudinary';
import { resolveCalendarMetaIcon } from '@/utils/icons';
import { useEvents } from '@/composables/useEvents';
import BaseButton from '@/components/ui/BaseButton.vue';
import TagList from '@/components/ui/TagList.vue';

interface Props {
    event: CalendarEntry | null;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { formatDateRange, formatPrice, getExcerpt } = useEvents();

const imageUrl = computed(() => buildCardImageUrl(props.event?.imagePublicId));

const isEventSource = computed(() => props.event?.sourceType === 'event');

const formattedDate = computed(() => {
    if (!props.event) return '';
    return formatDateRange(props.event.start, props.event.end ?? props.event.start);
});

const priceDisplay = computed(() => {
    if (!props.event) return '';

    const memberPrice = props.event.memberPrice ?? null;
    const nonMemberPrice = props.event.nonMemberPrice ?? null;

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

const metaRows = computed(() => props.event?.meta ?? []);
</script>

<template>
    <div
        class="flex h-full flex-col rounded-lg border-default bg-surface p-4 shadow-sm dark:shadow-neutral-900/50"
    >
        <!-- Empty state -->
        <div v-if="!event" class="flex flex-1 flex-col items-center justify-center text-center">
            <Calendar
                class="mx-auto h-12 w-12 text-neutral-300 dark:text-neutral-600"
                :stroke-width="1.5"
                aria-hidden="true"
            />
            <p class="mt-3 text-sm text-base-muted">
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
                <h3 class="text-lg font-semibold text-base-primary">
                    {{ event.title }}
                </h3>

                <!-- Tags -->
                <TagList
                    v-if="event.tags && event.tags.length > 0"
                    :tags="event.tags"
                    :linkable="false"
                    content-type="events"
                    class="mt-2"
                />

                <!-- Date -->
                <div class="mt-2 flex items-center text-sm text-base-secondary">
                    <Calendar class="mr-2 h-4 w-4 text-base-muted" aria-hidden="true" />
                    {{ formattedDate }}
                </div>

                <!-- Location -->
                <div
                    v-if="event.location"
                    class="mt-2 flex items-center text-sm text-base-secondary"
                >
                    <MapPin class="mr-2 h-4 w-4 text-base-muted" aria-hidden="true" />
                    {{ event.location }}
                </div>

                <!-- Source-provided rows (e.g. game system, players), rendered generically -->
                <div
                    v-for="row in metaRows"
                    :key="row.label"
                    class="mt-2 flex items-center text-sm text-base-secondary"
                >
                    <component
                        :is="resolveCalendarMetaIcon(row.icon)"
                        class="mr-2 h-4 w-4 shrink-0 text-base-muted"
                        :stroke-width="2"
                        aria-hidden="true"
                    />
                    <span>
                        <span class="text-base-muted">{{ row.label }}:</span>
                        {{ row.value }}
                    </span>
                </div>

                <!-- Description -->
                <p v-if="truncatedDescription" class="mt-3 text-sm text-base-secondary">
                    {{ truncatedDescription }}
                </p>

                <!-- Pricing (events only; other activity types don't have core pricing) -->
                <div v-if="isEventSource && priceDisplay" class="mt-3 flex items-center text-sm">
                    <Ticket class="mr-2 h-4 w-4 text-base-muted" aria-hidden="true" />
                    <span class="text-base-secondary">{{ priceDisplay }}</span>
                </div>
            </div>

            <!-- Action button -->
            <div class="mt-4 pt-4 border-t border-default">
                <Link :href="event.url" class="block">
                    <BaseButton variant="primary" size="sm" class="w-full">
                        {{ isEventSource ? t('calendar.viewEvent') : t('calendar.viewDetail') }}
                    </BaseButton>
                </Link>
            </div>
        </template>
    </div>
</template>
