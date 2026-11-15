<script setup lang="ts">
import type { ProfileTextSegment } from '@/types/profileSections';

/**
 * «Sobre mí» (`core.about`): the owner's text, cut on the server into lines and pieces of text and
 * links. Every piece is drawn as text, never as HTML, and only http/https addresses arrive as links.
 */
defineProps<{
    lines: ProfileTextSegment[][];
}>();
</script>

<template>
    <p class="break-words text-sm leading-relaxed text-base-secondary">
        <template v-for="(line, lineIndex) in lines" :key="lineIndex">
            <br v-if="lineIndex > 0" />
            <template v-for="(segment, segmentIndex) in line" :key="segmentIndex">
                <a
                    v-if="segment.href"
                    :href="segment.href"
                    rel="nofollow ugc noopener"
                    class="rounded-sm text-primary underline underline-offset-2 hover:text-base-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
                    >{{ segment.text }}</a
                ><template v-else>{{ segment.text }}</template>
            </template>
        </template>
    </p>
</template>
