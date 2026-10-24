<script setup lang="ts">
import type { Component } from 'vue';
import { Calendar, FileText, Image } from '@lucide/vue';

type PlaceholderVariant = 'event' | 'article' | 'gallery';

interface Props {
    variant: PlaceholderVariant;
    class?: string;
    height?: string;
    iconSize?: string;
}

withDefaults(defineProps<Props>(), {
    class: '',
    height: 'h-48',
    iconSize: 'h-16 w-16',
});

const icons: Record<PlaceholderVariant, Component> = {
    event: Calendar,
    article: FileText,
    gallery: Image,
};
</script>

<template>
    <div
        :class="[
            'flex aspect-video w-full items-center justify-center bg-gradient-to-br from-primary-500 to-neutral-600 dark:from-primary-600 dark:to-neutral-700',
            height,
            $props.class,
        ]"
    >
        <component :is="icons[variant]" :class="['text-white/50', iconSize]" aria-hidden="true" />
    </div>
</template>
