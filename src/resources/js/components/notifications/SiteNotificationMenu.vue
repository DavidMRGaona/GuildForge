<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, useId, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { Ellipsis, EyeOff, MailCheck, MailOpen } from '@lucide/vue';
import { useOnNavigate } from '@/composables/useOnNavigate';

/**
 * The «⋯» of one notice (core#670): mark it read or unread again without opening it, or hide it
 * for the person. A disclosure: the button opens a short list of buttons, Tab moves through them
 * and Escape closes it and gives the focus back (without closing the bell around it).
 */
interface Props {
    title: string;
    unread: boolean;
    /** While the server answers: the buttons wait. */
    busy?: boolean;
}

const props = withDefaults(defineProps<Props>(), { busy: false });

const emit = defineEmits<{
    'toggle-read': [];
    hide: [];
}>();

const { t } = useI18n();

const isOpen = ref(false);
const root = useTemplateRef<HTMLElement>('root');
const trigger = useTemplateRef<HTMLButtonElement>('trigger');
const panelId = useId();

const label = computed(() => t('notifications.actions.menu', { title: props.title }));

function close(returnFocus: boolean): void {
    isOpen.value = false;
    if (returnFocus) {
        void nextTick(() => trigger.value?.focus());
    }
}

function toggle(): void {
    if (props.busy) {
        return;
    }
    isOpen.value = !isOpen.value;
}

function onEscape(event: KeyboardEvent): void {
    if (!isOpen.value) {
        return;
    }
    // Only the menu closes: the bell around it stays open
    event.stopPropagation();
    event.preventDefault();
    close(true);
}

/**
 * The focus goes back to the «⋯» first: it stays there while the server answers and after a
 * failure; when the notice is hidden, the list moves it on to the notice now in its place.
 */
function choose(action: 'toggle-read' | 'hide'): void {
    close(true);
    if (action === 'toggle-read') {
        emit('toggle-read');
    } else {
        emit('hide');
    }
}

function onClickOutside(event: MouseEvent): void {
    const target = event.target;
    if (isOpen.value && target instanceof Node && root.value && !root.value.contains(target)) {
        close(false);
    }
}

function onFocusOut(event: FocusEvent): void {
    const next = event.relatedTarget;
    if (isOpen.value && next instanceof Node && root.value && !root.value.contains(next)) {
        close(false);
    }
}

useOnNavigate(() => close(false));

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
    <div ref="root" class="relative" @keydown.escape="onEscape" @focusout="onFocusOut">
        <button
            ref="trigger"
            type="button"
            :aria-label="label"
            :title="label"
            :aria-expanded="isOpen"
            :aria-controls="panelId"
            :aria-disabled="props.busy ? 'true' : undefined"
            class="rounded-md p-1.5 text-base-secondary hover:bg-muted hover:text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 aria-disabled:opacity-50"
            data-testid="site-notification-menu"
            @click.stop.prevent="toggle"
        >
            <Ellipsis class="h-4 w-4" aria-hidden="true" />
        </button>
        <div
            v-if="isOpen"
            :id="panelId"
            class="absolute right-0 z-10 mt-1 w-52 overflow-hidden rounded-md border border-default bg-surface py-1 shadow-lg"
        >
            <button
                type="button"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-base-primary hover:bg-muted focus:outline-none focus-visible:bg-muted"
                data-testid="site-notification-toggle-read"
                @click.stop.prevent="choose('toggle-read')"
            >
                <component
                    :is="props.unread ? MailOpen : MailCheck"
                    class="h-4 w-4 shrink-0 text-base-secondary"
                    aria-hidden="true"
                />
                {{
                    props.unread
                        ? t('notifications.actions.markRead')
                        : t('notifications.actions.markUnread')
                }}
            </button>
            <button
                type="button"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-base-primary hover:bg-muted focus:outline-none focus-visible:bg-muted"
                data-testid="site-notification-hide"
                @click.stop.prevent="choose('hide')"
            >
                <EyeOff class="h-4 w-4 shrink-0 text-base-secondary" aria-hidden="true" />
                {{ t('notifications.actions.hide') }}
            </button>
        </div>
    </div>
</template>
