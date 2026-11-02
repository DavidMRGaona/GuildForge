<script setup lang="ts">
import { CalendarPlus, ChevronDown, Download, ExternalLink } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AddToCalendarLinks } from '@/types/models';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useOnNavigate } from '@/composables/useOnNavigate';

interface Props {
    /** Links the backend builds for the entry (`calendarLinks` in calendar entries and pages). */
    links: AddToCalendarLinks;
    /** Where the menu opens; 'top' for buttons at the bottom of a panel. */
    placement?: 'bottom' | 'top';
    /** Stretch the button (and its menu) to the container's width. */
    block?: boolean;
    size?: 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
    placement: 'bottom',
    block: false,
    size: 'md',
});

const { t } = useI18n();

const isOpen = ref(false);
const containerRef = ref<HTMLElement | null>(null);

function toggle(): void {
    isOpen.value = !isOpen.value;
}

function close(): void {
    isOpen.value = false;
}

function closeAndFocusTrigger(): void {
    if (!isOpen.value) return;
    close();
    containerRef.value?.querySelector<HTMLButtonElement>('button[aria-haspopup]')?.focus();
}

function handleClickOutside(event: MouseEvent): void {
    const target = event.target as Node | null;
    if (containerRef.value && target && !containerRef.value.contains(target)) {
        close();
    }
}

useOnNavigate(close);

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div
        ref="containerRef"
        class="relative"
        :class="props.block ? 'block' : 'inline-block'"
        @keydown.escape="closeAndFocusTrigger"
    >
        <BaseButton
            variant="secondary"
            :size="props.size"
            :class="{ 'w-full': props.block }"
            aria-haspopup="menu"
            :aria-expanded="isOpen"
            @click="toggle"
        >
            <CalendarPlus class="h-4 w-4" aria-hidden="true" />
            {{ t('calendar.addToCalendar.button') }}
            <ChevronDown
                class="h-4 w-4 transition-transform"
                :class="{ 'rotate-180': isOpen }"
                aria-hidden="true"
            />
        </BaseButton>

        <div
            v-if="isOpen"
            class="absolute z-50 min-w-56 rounded-md border-default bg-surface shadow-lg ring-1 ring-black/5"
            :class="[
                props.placement === 'top' ? 'bottom-full mb-2' : 'top-full mt-2',
                props.block ? 'inset-x-0' : 'left-0',
            ]"
        >
            <div class="py-1" role="menu" :aria-label="t('calendar.addToCalendar.button')">
                <a
                    :href="props.links.google"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex w-full items-center gap-3 px-4 py-2 text-sm text-base-primary hover:bg-muted"
                    role="menuitem"
                    @click="close"
                >
                    <ExternalLink class="h-4 w-4 shrink-0 text-base-muted" aria-hidden="true" />
                    {{ t('calendar.addToCalendar.google') }}
                    <span class="sr-only">({{ t('calendar.addToCalendar.opensInNewTab') }})</span>
                </a>
                <a
                    :href="props.links.outlook"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex w-full items-center gap-3 px-4 py-2 text-sm text-base-primary hover:bg-muted"
                    role="menuitem"
                    @click="close"
                >
                    <ExternalLink class="h-4 w-4 shrink-0 text-base-muted" aria-hidden="true" />
                    {{ t('calendar.addToCalendar.outlook') }}
                    <span class="sr-only">({{ t('calendar.addToCalendar.opensInNewTab') }})</span>
                </a>
                <a
                    v-if="props.links.ics"
                    :href="props.links.ics"
                    class="flex w-full items-center gap-3 px-4 py-2 text-sm text-base-primary hover:bg-muted"
                    role="menuitem"
                    @click="close"
                >
                    <Download class="h-4 w-4 shrink-0 text-base-muted" aria-hidden="true" />
                    {{ t('calendar.addToCalendar.ics') }}
                </a>
            </div>
        </div>
    </div>
</template>
