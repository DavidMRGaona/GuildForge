<script setup lang="ts">
import type { SearchHit } from '@/types/search';

/** One suggestion of the header panel: title, type and date when it has one. */
type SuggestionOption = Pick<SearchHit, 'title' | 'url' | 'typeLabel' | 'date'>;

defineProps<{
    id: string;
    label: string;
    options: readonly SuggestionOption[];
    activeIndex: number;
    optionId: (index: number) => string;
}>();

const emit = defineEmits<{
    select: [url: string];
}>();

function details(option: SuggestionOption): string {
    return [option.typeLabel, option.date].filter((part): part is string => !!part).join(' · ');
}
</script>

<template>
    <ul :id="id" role="listbox" :aria-label="label" class="p-2">
        <!-- Focus stays in the combobox: options are picked with the mouse or with Enter -->
        <li
            v-for="(option, index) in options"
            :id="optionId(index)"
            :key="option.url"
            role="option"
            :aria-selected="index === activeIndex"
            class="cursor-pointer rounded-md px-3 py-2"
            :class="index === activeIndex ? 'bg-muted' : 'hover:bg-muted'"
            @mousedown.prevent
            @click="emit('select', option.url)"
        >
            <span class="block font-medium text-base-primary">{{ option.title }}</span>
            <span class="block text-sm text-base-secondary">{{ details(option) }}</span>
        </li>
    </ul>
</template>
