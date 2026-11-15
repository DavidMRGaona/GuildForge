<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { FooterLogo } from '@/types/footerBlocks';
import { isInertiaPage } from '@/utils/inertiaLinks';

/**
 * Footer block `core.logos`: a row of logos, each with its alternative text and an optional link
 * (core#698). On a light chip: logos are made for light backgrounds and the footer is dark in both
 * colour schemes.
 */
defineProps<{
    logos: FooterLogo[];
}>();

const chipClasses =
    'flex h-14 items-center justify-center rounded-md bg-white px-3 py-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
</script>

<template>
    <ul v-if="logos.length > 0" class="flex flex-wrap items-center gap-3">
        <li v-for="(logo, index) in logos" :key="`${index}-${logo.src}`">
            <component
                :is="logo.url && isInertiaPage(logo.url) ? Link : logo.url ? 'a' : 'span'"
                :href="logo.url ?? undefined"
                :class="chipClasses"
            >
                <img
                    :src="logo.src"
                    :alt="logo.alt"
                    :width="logo.width ?? undefined"
                    :height="logo.height ?? undefined"
                    loading="lazy"
                    class="max-h-10 w-auto max-w-32 object-contain"
                />
            </component>
        </li>
    </ul>
</template>
