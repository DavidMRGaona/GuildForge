<script setup lang="ts">
import { MessagesSquare } from '@lucide/vue';
import { computed, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import type { SubjectDiscussion } from '@/types/models';
import { VENUE_TIMEZONE } from '@/utils/datetime';

interface Props {
    /** The page's `discussion` prop (`SubjectDiscussionInterface`); null shows nothing. */
    discussion: SubjectDiscussion | null | undefined;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

const headingId = `subject-discussion-${useId()}`;

const own = computed(() => props.discussion?.discussion ?? null);
const topics = computed(() => props.discussion?.topics ?? []);
const visible = computed(() => own.value !== null || topics.value.length > 0);

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(locale.value, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: VENUE_TIMEZONE,
    });
}
</script>

<template>
    <section
        v-if="visible"
        class="rounded-lg border border-default p-4"
        :aria-labelledby="headingId"
    >
        <h2 :id="headingId" class="mb-2 flex items-center gap-2 font-semibold text-base-primary">
            <MessagesSquare class="h-5 w-5 text-primary" aria-hidden="true" />
            {{ t('discussions.title') }}
        </h2>

        <template v-if="own">
            <p class="text-sm text-base-secondary">
                <a
                    :href="own.url"
                    class="inline-flex min-h-[44px] items-center font-medium text-primary hover:text-primary-700 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {{ t('discussions.messages', { count: own.commentCount }, own.commentCount) }}
                    <span class="sr-only"> ({{ t('discussions.opensInNewTab') }})</span>
                </a>
                <template v-if="own.lastPostedAt">
                    · {{ t('discussions.lastPostedAt', { date: formatDate(own.lastPostedAt) }) }}
                </template>
            </p>
            <a
                :href="own.actionUrl"
                class="mt-3 inline-flex min-h-[44px] w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                {{ t('discussions.comment') }}
            </a>
        </template>

        <ul v-if="topics.length > 0" class="mt-3 text-sm text-base-secondary">
            <li v-for="topic in topics" :key="topic.url">
                <a
                    :href="topic.url"
                    class="inline-flex min-h-[44px] items-center hover:text-primary hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {{
                        t(
                            'discussions.topic',
                            { count: topic.discussionCount, label: topic.label },
                            topic.discussionCount
                        )
                    }}
                    <span class="sr-only"> ({{ t('discussions.opensInNewTab') }})</span>
                </a>
                <template v-if="topic.lastPostedAt">
                    · {{ t('discussions.lastPostedAt', { date: formatDate(topic.lastPostedAt) }) }}
                </template>
            </li>
        </ul>
    </section>
</template>
