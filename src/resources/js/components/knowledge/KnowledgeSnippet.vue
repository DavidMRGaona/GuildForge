<script setup lang="ts">
import type { KnowledgeSnippetSegment } from '@/types/knowledge';

defineProps<{
    segments: KnowledgeSnippetSegment[];
}>();
</script>

<template>
    <!-- Segments are plain text: never rendered as HTML -->
    <span>
        <template v-for="(segment, index) in segments" :key="index">
            <mark
                v-if="segment.match"
                class="rounded-sm bg-primary-light px-0.5 text-base-primary"
                >{{ segment.text }}</mark
            >
            <template v-else>{{ segment.text }}</template>
        </template>
    </span>
</template>
