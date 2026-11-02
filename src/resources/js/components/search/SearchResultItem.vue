<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import KnowledgeSnippet from '@/components/knowledge/KnowledgeSnippet.vue';
import type { SearchHit } from '@/types/search';
import { buildImageUrl } from '@/utils/cloudinary';

const props = defineProps<{
    hit: SearchHit;
}>();

const thumbnailUrl = computed(() =>
    buildImageUrl(props.hit.image, { width: 160, height: 160, crop: 'fill', trim: true })
);

/** «sáb 15 nov · 10:00 · Local de la asociación»: whatever of the two the hit has. */
const details = computed(() =>
    [props.hit.date, props.hit.meta].filter((part): part is string => !!part).join(' · ')
);
</script>

<template>
    <Link
        :href="hit.url"
        class="flex gap-4 rounded-lg px-3 py-3 transition-colors hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 sm:px-4"
    >
        <img
            v-if="thumbnailUrl"
            :src="thumbnailUrl"
            alt=""
            width="80"
            height="80"
            loading="lazy"
            class="h-16 w-16 shrink-0 rounded-md bg-muted object-cover sm:h-20 sm:w-20"
        />
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span data-testid="search-hit-title" class="font-semibold text-base-primary">{{
                    hit.title
                }}</span>
                <span
                    data-testid="search-hit-type"
                    class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-base-secondary"
                    >{{ hit.typeLabel }}</span
                >
                <span
                    v-if="hit.pastLabel"
                    data-testid="search-hit-past"
                    class="rounded-full border border-default px-2 py-0.5 text-xs text-base-muted"
                    >{{ hit.pastLabel }}</span
                >
            </span>
            <span
                v-if="details"
                data-testid="search-hit-details"
                class="mt-0.5 block text-sm text-base-secondary"
                >{{ details }}</span
            >
            <KnowledgeSnippet
                v-if="hit.snippet.length > 0"
                :segments="hit.snippet"
                class="mt-1 line-clamp-2 text-sm text-base-secondary"
            />
        </span>
    </Link>
</template>
