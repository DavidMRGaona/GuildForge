<script setup lang="ts">
import { computed, nextTick, ref, useId, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Search, X } from '@lucide/vue';
import { useModalDialog } from '@/composables/useModalDialog';
import { useOnNavigate } from '@/composables/useOnNavigate';
import { useRoutes } from '@/composables/useRoutes';
import { useSearchShortcut } from '@/composables/useSearchShortcut';
import { MIN_CHARS, useSearchSuggestions } from '@/composables/useSearchSuggestions';
import SearchSuggestionList from './SearchSuggestionList.vue';

const props = defineProps<{
    open: boolean;
    /**
     * Where focus goes when the person closes the panel, given the element focused before it
     * opened (null when that was the body). Without it, focus goes back to that element.
     */
    returnFocus?: (opener: HTMLElement | null) => HTMLElement | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const FOCUSABLE = 'input, button:not([disabled]), a[href]';

const { t } = useI18n();
const routes = useRoutes();
const uid = useId();
const { query, results, total, loading, failed, reset } = useSearchSuggestions();

const dialogRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);
const activeIndex = ref(-1);
let opener: HTMLElement | null = null;
// Whether the last press started on the backdrop itself: a text selection started in the field
// and released over the backdrop also ends in a click there, and must not close the panel
let pressedOnBackdrop = false;

const { trapTab } = useModalDialog(() => props.open, dialogRef, FOCUSABLE);

const term = computed(() => query.value.trim());
const isExpanded = computed(() => results.value.length > 0);
const activeId = computed(() => (activeIndex.value >= 0 ? optionId(activeIndex.value) : undefined));
const resultsUrl = computed(() => `${routes.search}?q=${encodeURIComponent(term.value)}`);
const status = computed(() => {
    if (term.value.length < MIN_CHARS || loading.value || failed.value) {
        return '';
    }
    const count = results.value.length;
    return t('search.suggestionsCount', { count }, count);
});

function optionId(index: number): string {
    return `${uid}-option-${index}`;
}

function onBackdropPointerDown(event: MouseEvent): void {
    pressedOnBackdrop = event.target === event.currentTarget;
}

function onBackdropClick(): void {
    if (pressedOnBackdrop) {
        close();
    }
    pressedOnBackdrop = false;
}

function close(restoreFocus = true): void {
    if (!props.open) {
        return;
    }
    emit('update:open', false);
    if (restoreFocus) {
        const target = props.returnFocus ? props.returnFocus(opener) : opener;
        void nextTick(() => target?.focus());
    }
}

function visit(url: string): void {
    // Closed first: the visit swaps the page under the panel and a left-over panel would hide it
    close(false);
    router.visit(url);
}

function goToResults(): void {
    if (term.value !== '') {
        visit(resultsUrl.value);
    }
}

function onSeeAll(event: MouseEvent): void {
    // A new tab or window leaves the panel as it is
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }
    event.preventDefault();
    goToResults();
}

function onInputKeydown(event: KeyboardEvent): void {
    const count = results.value.length;
    if (event.isComposing) {
        return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (count > 0) {
            const step = event.key === 'ArrowDown' ? 1 : -1;
            activeIndex.value =
                activeIndex.value < 0 && step < 0
                    ? count - 1
                    : (activeIndex.value + step + count) % count;
        }
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const hit = results.value[activeIndex.value];
        if (hit) {
            visit(hit.url);
        } else {
            goToResults();
        }
    }
}

function onDialogKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }
    if (event.key === 'Tab') {
        trapTab(event);
    }
}

// New suggestions, or a query they no longer answer: nothing stays marked. Synchronous, so an
// Enter right after typing never opens a suggestion for the previous query
watch(results, () => {
    activeIndex.value = -1;
});
watch(
    query,
    () => {
        activeIndex.value = -1;
    },
    { flush: 'sync' }
);

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) {
            activeIndex.value = -1;
            reset();
            return;
        }
        const focused = document.activeElement;
        opener = focused instanceof HTMLElement && focused !== document.body ? focused : null;
        await nextTick();
        inputRef.value?.focus();
    },
    { immediate: true }
);

useSearchShortcut(
    () => emit('update:open', true),
    () => props.open
);
useOnNavigate(() => close(false));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 bg-neutral-900/60 md:px-4 md:pt-16"
            @mousedown="onBackdropPointerDown"
            @click.self="onBackdropClick"
        >
            <div
                ref="dialogRef"
                role="dialog"
                aria-modal="true"
                tabindex="-1"
                :aria-label="t('search.panelLabel')"
                class="flex h-full flex-col bg-elevated md:mx-auto md:h-auto md:max-h-[calc(100dvh-8rem)] md:max-w-2xl md:rounded-lg md:border md:border-default md:shadow-xl dark:shadow-neutral-900/50"
                @keydown="onDialogKeydown"
            >
                <div role="search" class="flex items-center gap-2 border-b border-default p-2">
                    <Search class="ml-2 h-5 w-5 shrink-0 text-base-muted" aria-hidden="true" />
                    <label :for="`${uid}-input`" class="sr-only">{{
                        t('search.panelLabel')
                    }}</label>
                    <input
                        :id="`${uid}-input`"
                        ref="inputRef"
                        v-model="query"
                        type="text"
                        role="combobox"
                        autocomplete="off"
                        enterkeyhint="search"
                        aria-autocomplete="list"
                        :aria-expanded="isExpanded"
                        :aria-controls="`${uid}-listbox`"
                        :aria-activedescendant="activeId"
                        :placeholder="t('search.placeholder')"
                        class="min-w-0 flex-1 rounded-md bg-transparent px-2 py-2 text-base text-base-primary placeholder:text-base-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        @keydown="onInputKeydown"
                    />
                    <button
                        type="button"
                        :aria-label="t('common.close')"
                        class="shrink-0 rounded-md p-2 text-base-secondary hover:bg-muted hover:text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        @click="close()"
                    >
                        <X class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <SearchSuggestionList
                        v-show="isExpanded"
                        :id="`${uid}-listbox`"
                        :label="t('search.suggestionsLabel')"
                        :options="results"
                        :active-index="activeIndex"
                        :option-id="optionId"
                        @select="visit"
                    />
                    <!-- Announces the count; shown only when there is nothing to list -->
                    <p
                        aria-live="polite"
                        :class="
                            isExpanded || !status ? 'sr-only' : 'px-5 py-4 text-sm text-base-muted'
                        "
                    >
                        {{ status }}
                    </p>
                </div>

                <a
                    v-if="total > 0"
                    data-testid="search-panel-see-all"
                    :href="resultsUrl"
                    class="block border-t border-default px-5 py-3 text-sm font-medium text-primary hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
                    @click="onSeeAll"
                    >{{ t('search.seeAll', { count: total }) }}</a
                >
            </div>
        </div>
    </Teleport>
</template>
