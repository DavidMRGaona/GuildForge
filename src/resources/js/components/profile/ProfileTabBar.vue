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
    <nav
        class="flex gap-1 overflow-x-auto border-b border-default bg-surface px-4"
        aria-label="Profile navigation"
    >
        <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            :class="[
                'relative flex shrink-0 items-center gap-2 px-4 py-3 text-sm font-medium transition-colors',
                activeTabId === tab.id
                    ? 'text-primary-600 dark:text-primary-400'
                    : 'text-base-muted hover:text-base-secondary',
            ]"
            :aria-current="activeTabId === tab.id ? 'page' : undefined"
            @click="handleTabClick(tab.id)"
        >
            <ProfileTabIcon :icon="tab.icon" class="h-5 w-5 shrink-0" />
            <span>{{ tab.label }}</span>
            <span
                v-if="tab.badge !== undefined && tab.badge > 0"
                class="inline-flex items-center justify-center rounded-full bg-primary-500 px-1.5 py-0.5 text-xs font-semibold text-white"
            >
                {{ tab.badge }}
            </span>

            <!-- Active indicator -->
            <span
                v-if="activeTabId === tab.id"
                class="absolute inset-x-0 bottom-0 h-0.5 bg-primary-500"
                aria-hidden="true"
            />
        </button>
    </nav>
</template>
