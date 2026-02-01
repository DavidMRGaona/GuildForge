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

function isChildItem(tab: ProfileTab): boolean {
    return tab.parentId !== undefined;
}
</script>

<template>
    <nav class="sticky top-24 space-y-1" aria-label="Profile navigation">
        <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            :class="[
                'flex w-full items-center gap-3 rounded-lg text-left transition-colors',
                isChildItem(tab) ? 'py-2.5 pl-10 pr-4 text-sm' : 'px-4 py-3 text-sm font-medium',
                activeTabId === tab.id
                    ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                    : 'text-base-secondary hover:bg-neutral-100 hover:text-base-primary dark:hover:bg-neutral-800',
            ]"
            :aria-current="activeTabId === tab.id ? 'page' : undefined"
            @click="handleTabClick(tab.id)"
        >
            <ProfileTabIcon :icon="tab.icon" :class="isChildItem(tab) ? 'h-4 w-4 shrink-0' : 'h-5 w-5 shrink-0'" />
            <span class="flex-1">{{ tab.label }}</span>
            <span
                v-if="tab.badge !== undefined && tab.badge > 0"
                class="inline-flex items-center justify-center rounded-full bg-primary-500 px-2 py-0.5 text-xs font-semibold text-white"
            >
                {{ tab.badge }}
            </span>
        </button>
    </nav>
</template>
