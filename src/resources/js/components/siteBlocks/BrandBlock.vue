<script setup lang="ts">
import { computed } from 'vue';
import SocialLinks from '@/components/ui/SocialLinks.vue';
import type { SocialMediaLinks } from '@/types/models';

/**
 * Footer block `core.brand`: the site's logo, name, a short text and its social networks (core#697).
 * The footer is dark in both colour schemes, so the logo is the dark-scheme one.
 */
const props = defineProps<{
    logo: string | null;
    name: string | null;
    description: string | null;
    socialLinks: SocialMediaLinks;
}>();

const hasSocialLinks = computed(() => Object.values(props.socialLinks).some((url) => url));
</script>

<template>
    <div class="flex flex-col items-start gap-4 text-start">
        <img
            v-if="logo"
            :src="logo"
            :alt="name ?? ''"
            class="h-12 w-auto max-w-full object-contain"
        />
        <p v-if="name" class="text-base font-semibold text-white">{{ name }}</p>
        <p
            v-if="description"
            class="whitespace-pre-line text-sm text-neutral-300 dark:text-neutral-400"
        >
            {{ description }}
        </p>
        <SocialLinks v-if="hasSocialLinks" :links="socialLinks" size="sm" variant="dark" />
    </div>
</template>
