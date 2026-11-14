<script setup lang="ts">
import { provide, useId } from 'vue';
import SiteBlock from '@/components/layout/SiteBlock.vue';
import { FOOTER_BLOCK_TITLE_ID } from '@/components/siteBlocks/footerBlockTitle';
import type { FooterBlockEntry } from '@/types/footer';

/**
 * One block of the configurable footer with its optional title (core#697). The section is named after
 * its title, and the block learns the title's id to name its own landmark.
 */
const props = defineProps<{
    entry: FooterBlockEntry;
}>();

const titleId = useId();

provide(FOOTER_BLOCK_TITLE_ID, props.entry.title ? titleId : null);
</script>

<template>
    <section class="min-w-0" :aria-labelledby="entry.title ? titleId : undefined">
        <h2 v-if="entry.title" :id="titleId" class="mb-3 text-base font-semibold text-white">
            {{ entry.title }}
        </h2>
        <SiteBlock :block="entry.block" />
    </section>
</template>
