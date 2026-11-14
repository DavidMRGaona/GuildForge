<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { FooterLogo } from '@/types/footerBlocks';

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
            <a v-if="logo.url && logo.external" :href="logo.url" :class="chipClasses">
                <img
                    :src="logo.src"
                    :alt="logo.alt"
                    loading="lazy"
                    class="max-h-10 w-auto max-w-32 object-contain"
                />
            </a>
            <Link v-else-if="logo.url" :href="logo.url" :class="chipClasses">
                <img
                    :src="logo.src"
                    :alt="logo.alt"
                    loading="lazy"
                    class="max-h-10 w-auto max-w-32 object-contain"
                />
            </Link>
            <span v-else :class="chipClasses">
                <img
                    :src="logo.src"
                    :alt="logo.alt"
                    loading="lazy"
                    class="max-h-10 w-auto max-w-32 object-contain"
                />
            </span>
        </li>
    </ul>
</template>
