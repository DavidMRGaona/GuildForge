<script setup lang="ts">
import { CalendarDays, Check, ChevronDown, Copy, ExternalLink, Rss } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useNotifications } from '@/composables/useNotifications';
import { useOnNavigate } from '@/composables/useOnNavigate';
import type { CalendarSubscriptionLinks } from '@/types/calendarFeed';

interface Props {
    /** Addresses the backend builds for the feed (`CalendarSubscriptionLinksDTO`). */
    links: CalendarSubscriptionLinks;
    /**
     * 'start' opens the menu rightwards; 'end' (a button at the end of a row) opens it
     * leftwards from sm up and rightwards on phones, where the button has its own row.
     */
    align?: 'start' | 'end';
}

const props = withDefaults(defineProps<Props>(), {
    align: 'start',
});

const { t } = useI18n();
const notifications = useNotifications();

const isOpen = ref(false);
const copied = ref(false);
const containerRef = ref<HTMLElement | null>(null);

// Chrome, Firefox, Edge, Opera and the Google app on iPhone do not hand webcal:// to Calendar
const isIosOtherBrowser =
    typeof navigator !== 'undefined' && /CriOS|FxiOS|EdgiOS|OPiOS|GSA\//.test(navigator.userAgent);

const itemClass =
    'flex w-full items-center gap-3 whitespace-nowrap px-4 py-2 text-left text-sm text-base-primary hover:bg-muted focus:bg-muted focus:outline-none';

function toggle(): void {
    isOpen.value = !isOpen.value;
    copied.value = false;
}

function close(): void {
    isOpen.value = false;
}

function closeAndFocusTrigger(): void {
    if (!isOpen.value) return;
    close();
    containerRef.value?.querySelector<HTMLButtonElement>('button[aria-haspopup]')?.focus();
}

async function copyAddress(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.links.webcalUrl);
        copied.value = true;
    } catch (error) {
        console.error('Copying the calendar address failed:', error);
        notifications.error(t('calendar.subscribe.copyError'));
    }
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
    <div ref="containerRef" class="relative inline-block" @keydown.escape="closeAndFocusTrigger">
        <BaseButton
            variant="secondary"
            size="sm"
            aria-haspopup="menu"
            :aria-expanded="isOpen"
            @click="toggle"
        >
            <Rss class="h-4 w-4" aria-hidden="true" />
            {{ t('calendar.subscribe.button') }}
            <ChevronDown
                class="h-4 w-4 transition-transform"
                :class="{ 'rotate-180': isOpen }"
                aria-hidden="true"
            />
        </BaseButton>

        <div
            v-if="isOpen"
            class="absolute top-full z-50 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-md border border-default bg-surface shadow-lg ring-1 ring-black/5"
            :class="props.align === 'end' ? 'left-0 sm:left-auto sm:right-0' : 'left-0'"
        >
            <div class="py-1" role="menu" :aria-label="t('calendar.subscribe.menuLabel')">
                <a :href="props.links.webcalUrl" :class="itemClass" role="menuitem" @click="close">
                    <CalendarDays class="h-4 w-4 shrink-0 text-base-muted" aria-hidden="true" />
                    {{ t('calendar.subscribe.apple') }}
                </a>
                <a
                    :href="props.links.googleUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    :class="itemClass"
                    role="menuitem"
                    @click="close"
                >
                    <ExternalLink class="h-4 w-4 shrink-0 text-base-muted" aria-hidden="true" />
                    {{ t('calendar.subscribe.google') }}
                    <span class="sr-only">({{ t('calendar.addToCalendar.opensInNewTab') }})</span>
                </a>
                <a
                    :href="props.links.outlookUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    :class="itemClass"
                    role="menuitem"
                    @click="close"
                >
                    <ExternalLink class="h-4 w-4 shrink-0 text-base-muted" aria-hidden="true" />
                    {{ t('calendar.subscribe.outlook') }}
                    <span class="sr-only">({{ t('calendar.addToCalendar.opensInNewTab') }})</span>
                </a>
                <button type="button" :class="itemClass" role="menuitem" @click="copyAddress">
                    <Check
                        v-if="copied"
                        class="h-4 w-4 shrink-0 text-base-muted"
                        aria-hidden="true"
                    />
                    <Copy v-else class="h-4 w-4 shrink-0 text-base-muted" aria-hidden="true" />
                    {{ copied ? t('calendar.subscribe.copied') : t('calendar.subscribe.copy') }}
                </button>
            </div>
            <p class="border-t border-default px-4 py-2 text-xs text-base-muted">
                {{ t('calendar.subscribe.hint') }}
            </p>
            <p
                v-if="isIosOtherBrowser"
                data-testid="calendar-subscribe-ios-hint"
                class="px-4 pb-2 text-xs text-base-muted"
            >
                {{ t('calendar.subscribe.iosOtherBrowserHint') }}
            </p>
            <span class="sr-only" role="status" aria-live="polite">
                {{ copied ? t('calendar.subscribe.copied') : '' }}
            </span>
        </div>
    </div>
</template>
