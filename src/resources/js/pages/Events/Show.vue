<script setup lang="ts">
import { Calendar, ChevronLeft, Clock, Download, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Event } from '@/types/models';
import BaseButton from '@/components/ui/BaseButton.vue';
import TagBadge from '@/components/ui/TagBadge.vue';
import TagList from '@/components/ui/TagList.vue';
import { useEvents } from '@/composables/useEvents';
import { useTags } from '@/composables/useTags';
import { useRoutes } from '@/composables/useRoutes';
import { buildHeroImageUrl } from '@/utils/cloudinary';
import ModuleSlot from '@/components/layout/ModuleSlot.vue';

interface Props {
    event: Event;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { formatDateRange, formatSchedule, formatPrice, isUpcoming } = useEvents();
const routes = useRoutes();

const heroImageUrl = computed(() => buildHeroImageUrl(props.event.imagePublicId));

const { categoryTag, additionalTags, hasTags } = useTags(computed(() => props.event.tags));
</script>

<template>
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6">
            <Link
                :href="routes.events.index"
                class="inline-flex items-center text-sm text-base-muted hover:text-base-primary focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-page"
            >
                <ChevronLeft class="mr-1 h-4 w-4" aria-hidden="true" />
                {{ t('common.back') }}
            </Link>
        </div>

        <article class="overflow-hidden rounded-lg bg-surface shadow dark:shadow-neutral-900/50">
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
                <Calendar class="h-24 w-24 text-white/50" aria-hidden="true" />
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
                        <Calendar class="mr-2 h-5 w-5 text-primary" aria-hidden="true" />
                        <span>{{
                            formatDateRange(props.event.startDate, props.event.endDate)
                        }}</span>
                    </div>

                    <div class="flex items-center">
                        <span class="sr-only">{{ t('events.schedule') }}:</span>
                        <Clock class="mr-2 h-5 w-5 text-primary" aria-hidden="true" />
                        <span>{{
                            formatSchedule(props.event.startDate, props.event.endDate)
                        }}</span>
                    </div>

                    <div v-if="props.event.location" class="flex items-center">
                        <span class="sr-only">{{ t('events.location') }}:</span>
                        <MapPin class="mr-2 h-5 w-5 text-primary" aria-hidden="true" />
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
                        <p v-if="props.event.nonMemberPrice !== null" class="text-base-secondary">
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

                <!-- Download links -->
                <div
                    v-if="props.event.downloadLinks.length > 0"
                    class="mb-6 rounded-lg border border-default p-4"
                >
                    <h3 class="mb-3 font-semibold text-base-primary">
                        {{ t('events.downloads') }}
                    </h3>
                    <ul class="space-y-3">
                        <li v-for="(link, index) in props.event.downloadLinks" :key="index">
                            <a
                                :href="link.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="group flex items-start gap-3"
                            >
                                <Download
                                    class="mt-0.5 h-5 w-5 shrink-0 text-primary group-hover:text-primary-700"
                                    aria-hidden="true"
                                />
                                <div>
                                    <span
                                        class="font-medium text-primary group-hover:text-primary-700 group-hover:underline"
                                    >
                                        {{ link.label }}
                                    </span>
                                    <p v-if="link.description" class="text-sm text-base-muted">
                                        {{ link.description }}
                                    </p>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Module slot for event actions (e.g., registration button) -->
                <div class="mb-6">
                    <ModuleSlot name="event-detail-actions" />
                </div>

                <!--
                    SECURITY: v-html is used to render rich text content from the event.
                    Content is sanitized server-side via SanitizesHtml trait (HTMLPurifier).
                    @see App\Infrastructure\Factories\EloquentResponseDTOFactory
                -->
                <!-- eslint-disable vue/no-v-html -->
                <div class="prose max-w-none dark:prose-invert" v-html="props.event.description" />
                <!-- eslint-enable vue/no-v-html -->

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
</template>
