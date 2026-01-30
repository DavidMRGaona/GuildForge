<script setup lang="ts">
import type { ProfileTab } from '@/types/profile';
import ProfileTabIcon from './ProfileTabIcon.vue';

interface Props {
    tabs: ProfileTab[];
    activeTabId: string;
}

defineProps<Props>();

const emit = defineEmits<{
    'select-tab': [id: string];
}>();

function handleTabClick(tabId: string): void {
    emit('select-tab', tabId);
}
</script>

<template>
    <nav class="sticky top-24 space-y-1" aria-label="Profile navigation">
        <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            :class="[
                'flex w-full items-center gap-3 rounded-lg px-4 py-3 text-left text-sm font-medium transition-colors',
                activeTabId === tab.id
                    ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900 dark:text-stone-400 dark:hover:bg-stone-800 dark:hover:text-stone-200',
            ]"
            :aria-current="activeTabId === tab.id ? 'page' : undefined"
            @click="handleTabClick(tab.id)"
        >
            <ProfileTabIcon :icon="tab.icon" class="h-5 w-5 shrink-0" />
            <span class="flex-1">{{ tab.label }}</span>
            <span
                v-if="tab.badge !== undefined && tab.badge > 0"
                class="inline-flex items-center justify-center rounded-full bg-amber-500 px-2 py-0.5 text-xs font-semibold text-white"
            >
                {{ tab.badge }}
            </span>
        </button>
    </nav>
</template>
