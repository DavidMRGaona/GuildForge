<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { Event, PaginatedResponse, Tag } from '@/types';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import EventList from '@/components/events/EventList.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import TagFilter from '@/components/ui/TagFilter.vue';
import { useSeo } from '@/composables/useSeo';
import { usePagination } from '@/composables/usePagination';
import { useRoutes } from '@/composables/useRoutes';

interface Props {
    events: PaginatedResponse<Event>;
    tags: Tag[];
    currentTags: string[];
}

const props = withDefaults(defineProps<Props>(), {
    tags: () => [],
    currentTags: () => [],
});

const { t } = useI18n();
const routes = useRoutes();

useSeo({
    title: t('events.title'),
    description: t('home.subtitle'),
});

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
    <DefaultLayout>
        <div class="bg-surface shadow dark:shadow-neutral-900/50">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                    {{ t('events.title') }}
                </h1>
                <p class="mt-2 text-lg text-base-secondary">
                    {{ t('home.subtitle') }}
                </p>
            </div>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
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
        </main>
    </DefaultLayout>
</template>
