<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Event, PaginatedResponse, Tag } from '@/types';
import EventList from '@/components/events/EventList.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import TagFilter from '@/components/ui/TagFilter.vue';
import { usePagination } from '@/composables/usePagination';
import { useRoutes } from '@/composables/useRoutes';
import HelpLink from '@/components/knowledge/HelpLink.vue';
import EventsViewToggle from '@/components/events/EventsViewToggle.vue';

interface Props {
    events: PaginatedResponse<Event>;
    tags?: Tag[];
    currentTags?: string[];
    /** Whether the person may propose an event (core#579). */
    canPropose?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    tags: () => [],
    currentTags: () => [],
    canPropose: false,
});

const { t } = useI18n();
const routes = useRoutes();

const {
    firstItemNumber,
    lastItemNumber,
    hasPagination,
    canGoPrev,
    canGoNext,
    isNavigating,
    handlePrev,
    handleNext,
} = usePagination(() => props.events);
</script>

<template>
    <div data-testid="page-band" class="page-band">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- The list/calendar toggle stays at the top right, also on phones -->
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                            {{ t('events.title') }}
                        </h1>
                        <HelpLink guide="core.events.find-events" />
                    </div>
                    <p class="mt-2 text-lg text-base-secondary">
                        {{ t('events.subtitle') }}
                    </p>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-3 sm:flex-row sm:items-center">
                    <Link
                        v-if="canPropose"
                        :href="routes.proposals.events.create"
                        data-testid="propose-event"
                        class="inline-flex min-h-[44px] shrink-0 items-center justify-center rounded-md bg-(--button-primary-bg) px-4 py-2 font-medium text-(--button-primary-text) inset-ring inset-ring-(--button-primary-border) hover:bg-(--button-primary-hover-bg) hover:text-(--button-primary-hover-text) focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                    >
                        {{ t('proposals.proposeEvent') }}
                    </Link>
                    <EventsViewToggle current="list" />
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <TagFilter
            :tags="props.tags"
            :current-tags="props.currentTags"
            :base-path="routes.events.index"
            class="mb-6"
        />

        <EventList :events="props.events.data" />

        <div
            v-if="hasPagination"
            class="mt-8 flex items-center justify-between border-t border-default pt-6"
        >
            <p class="text-sm text-base-secondary">
                {{ t('common.showing') }}
                <span class="font-medium">
                    {{ firstItemNumber }}
                </span>
                -
                <span class="font-medium">
                    {{ lastItemNumber }}
                </span>
                {{ t('common.of') }}
                <span class="font-medium">{{ props.events.meta.total }}</span>
            </p>

            <div class="flex gap-2">
                <BaseButton
                    variant="secondary"
                    :disabled="!canGoPrev"
                    :loading="isNavigating"
                    @click="handlePrev"
                >
                    {{ t('common.previous') }}
                </BaseButton>
                <BaseButton
                    variant="secondary"
                    :disabled="!canGoNext"
                    :loading="isNavigating"
                    @click="handleNext"
                >
                    {{ t('common.next') }}
                </BaseButton>
            </div>
        </div>
    </div>
</template>
