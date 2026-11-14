<script setup lang="ts">
import { Check, ChevronDown, Search } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePinnedPanel } from '@/composables/usePinnedPanel';
import {
    normalizeForSearch,
    visibleRows,
    wantsSearch,
    type SelectOption,
    type SelectRow,
} from './selectOptions';

/**
 * The site's dropdown (core#648): never the browser's own list. A button opens a list pinned to
 * it (core#636); from SEARCH_THRESHOLD options, or when `searchable` says so, the list starts
 * with a search box. Options may form a two-level tree (`level: 1` hangs from the level-0 option
 * above), drawn indented with dividers between blocks. It follows the ARIA select-only combobox
 * pattern: arrows, Home/End, Enter, Escape, Tab and type-to-find when there is no search box.
 */
const props = withDefaults(
    defineProps<{
        modelValue: string | number | null;
        options: SelectOption[];
        placeholder?: string | undefined;
        disabled?: boolean | undefined;
        error?: string | undefined;
        id?: string | undefined;
        name?: string | undefined;
        /** Ids of the hint and error that describe the field. */
        describedBy?: string | undefined;
        /** Search box in the list; by default from SEARCH_THRESHOLD options. */
        searchable?: boolean | undefined;
    }>(),
    {
        describedBy: undefined,
        searchable: undefined,
        placeholder: '',
        disabled: false,
        error: '',
        id: '',
        name: '',
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null];
}>();

const { t } = useI18n();

interface Entry {
    value: string | number;
    label: string;
    row: SelectRow | null;
    disabled: boolean;
}

const uid = useId();
const buttonId = computed(() => props.id || `${uid}-button`);
const listId = `${uid}-list`;

const open = ref(false);
const query = ref('');
const activeIndex = ref(-1);

const button = ref<HTMLButtonElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const listbox = ref<HTMLElement | null>(null);
const search = ref<HTMLInputElement | null>(null);

// The open list is pinned to the button, so a dialog or scrolling card never clips it (core#636)
const { style: panelStyle } = usePinnedPanel(button, panel);

const isSearchable = computed(() => wantsSearch(props.searchable, props.options.length));
const allRows = computed(() => visibleRows(props.options, ''));

const entries = computed<Entry[]>(() => {
    const searching = isSearchable.value && query.value.trim() !== '';
    const list: Entry[] = [];
    // The placeholder clears the field; while searching it would only be in the way
    if (props.placeholder && !searching) {
        list.push({ value: '', label: props.placeholder, row: null, disabled: false });
    }
    for (const row of visibleRows(props.options, searching ? query.value : '')) {
        list.push({
            value: row.option.value,
            label: row.option.label,
            row,
            disabled: row.option.disabled === true,
        });
    }

    return list;
});

const isEmpty = computed(() => props.modelValue === null || props.modelValue === '');

/** What the closed field shows: a child keeps its parent's name, so it is never ambiguous. */
const selectedLabel = computed(() => {
    if (isEmpty.value) {
        return props.placeholder;
    }
    const row = allRows.value.find((candidate) => candidate.option.value === props.modelValue);
    if (row === undefined) {
        return props.placeholder;
    }

    return row.parentLabel === null ? row.option.label : `${row.parentLabel} › ${row.option.label}`;
});

function optionId(index: number): string {
    return `${uid}-option-${index}`;
}

const activeId = computed(() =>
    open.value && entries.value[activeIndex.value] ? optionId(activeIndex.value) : undefined
);

function isSelected(entry: Entry): boolean {
    return entry.value !== '' && entry.value === props.modelValue;
}

function firstEnabled(from: number, step: 1 | -1): number {
    for (let index = from; index >= 0 && index < entries.value.length; index += step) {
        if (entries.value[index]?.disabled === false) {
            return index;
        }
    }

    return -1;
}

function scrollActiveIntoView(): void {
    void nextTick(() => {
        const option = activeId.value ? document.getElementById(activeId.value) : null;
        option?.scrollIntoView?.({ block: 'nearest' });
    });
}

function setActive(index: number): void {
    if (index < 0) {
        return;
    }
    activeIndex.value = index;
    scrollActiveIntoView();
}

function openList(): void {
    if (props.disabled || open.value) {
        return;
    }
    query.value = '';
    open.value = true;
    const selected = entries.value.findIndex((entry) => isSelected(entry));
    activeIndex.value = selected >= 0 ? selected : firstEnabled(0, 1);
    void nextTick(() => {
        (isSearchable.value ? search.value : listbox.value)?.focus({ preventScroll: true });
        scrollActiveIntoView();
    });
}

function closeList(refocus: boolean): void {
    if (!open.value) {
        return;
    }
    open.value = false;
    query.value = '';
    if (refocus) {
        button.value?.focus({ preventScroll: true });
    }
}

function toggle(): void {
    if (open.value) {
        closeList(true);
    } else {
        openList();
    }
}

function choose(entry: Entry | undefined): void {
    if (entry === undefined || entry.disabled) {
        return;
    }
    emit('update:modelValue', entry.value);
    closeList(true);
}

// A new query starts again from the first option it shows
watch(query, () => {
    activeIndex.value = firstEnabled(0, 1);
});

function hover(index: number): void {
    if (activeIndex.value !== index && entries.value[index]?.disabled === false) {
        activeIndex.value = index;
    }
}

function move(step: 1 | -1): void {
    const next = firstEnabled(activeIndex.value + step, step);
    setActive(next >= 0 ? next : activeIndex.value);
}

let typed = '';
let typedTimer: ReturnType<typeof setTimeout> | undefined;

/** Without a search box, typing jumps to the next option that starts with what was typed. */
function typeToFind(key: string): void {
    clearTimeout(typedTimer);
    typed += normalizeForSearch(key);
    typedTimer = setTimeout(() => {
        typed = '';
    }, 500);
    const count = entries.value.length;
    for (let offset = typed.length === 1 ? 1 : 0; offset <= count; offset++) {
        const index = (activeIndex.value + offset + count) % count;
        const entry = entries.value[index];
        if (entry && !entry.disabled && normalizeForSearch(entry.label).startsWith(typed)) {
            setActive(index);

            return;
        }
    }
}

function onButtonKeydown(event: KeyboardEvent): void {
    if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        openList();
    }
}

function onPanelKeydown(event: KeyboardEvent): void {
    const inSearch = event.target === search.value;
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            move(1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            move(-1);
            break;
        case 'Home':
        case 'End':
            // In the search box they move the caret
            if (!inSearch) {
                event.preventDefault();
                setActive(
                    event.key === 'Home'
                        ? firstEnabled(0, 1)
                        : firstEnabled(entries.value.length - 1, -1)
                );
            }
            break;
        case 'Enter':
            event.preventDefault();
            choose(entries.value[activeIndex.value]);
            break;
        case 'Escape':
            // Also keeps a <dialog> holding the field from closing
            event.preventDefault();
            event.stopPropagation();
            closeList(true);
            break;
        case 'Tab':
            event.preventDefault();
            closeList(true);
            break;
        case ' ':
            if (!inSearch) {
                event.preventDefault();
                choose(entries.value[activeIndex.value]);
            }
            break;
        default:
            if (
                !inSearch &&
                event.key.length === 1 &&
                !event.ctrlKey &&
                !event.metaKey &&
                !event.altKey
            ) {
                typeToFind(event.key);
            }
    }
}

function onOutsidePointer(event: PointerEvent): void {
    const target = event.target;
    if (
        !(target instanceof Node) ||
        button.value?.contains(target) ||
        panel.value?.contains(target)
    ) {
        return;
    }
    closeList(false);
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('pointerdown', onOutsidePointer, true);
    } else {
        document.removeEventListener('pointerdown', onOutsidePointer, true);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onOutsidePointer, true);
    clearTimeout(typedTimer);
});
</script>

<template>
    <div class="relative">
        <button
            :id="buttonId"
            ref="button"
            type="button"
            :name="name || undefined"
            :disabled="disabled"
            aria-haspopup="listbox"
            :aria-expanded="open ? 'true' : 'false'"
            :aria-controls="open ? listId : undefined"
            :aria-describedby="describedBy"
            :aria-invalid="error ? 'true' : undefined"
            class="relative w-full rounded-lg border bg-surface px-4 py-2.5 pr-10 text-left transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 disabled:bg-muted disabled:cursor-not-allowed"
            :class="[
                error
                    ? 'border-error focus:ring-error focus:border-error'
                    : 'border-default hover:border-strong',
                isEmpty ? 'text-base-muted' : 'text-base-primary',
            ]"
            @click="toggle"
            @keydown="onButtonKeydown"
        >
            <span class="block truncate">{{ selectedLabel }}</span>
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                <ChevronDown
                    class="h-5 w-5 text-base-muted transition-transform"
                    :class="{ 'rotate-180': open }"
                    aria-hidden="true"
                />
            </span>
        </button>

        <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div
                v-if="open"
                ref="panel"
                :style="panelStyle"
                data-select-panel
                class="z-50 overflow-hidden rounded-lg border border-default bg-surface shadow-lg"
                @keydown="onPanelKeydown"
            >
                <div v-if="isSearchable" class="border-b border-default p-2">
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-muted"
                            aria-hidden="true"
                        />
                        <input
                            ref="search"
                            v-model="query"
                            type="text"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-expanded="true"
                            :aria-controls="listId"
                            :aria-activedescendant="activeId"
                            :aria-label="t('common.selectSearchLabel')"
                            :placeholder="t('common.selectSearchPlaceholder')"
                            autocomplete="off"
                            spellcheck="false"
                            class="w-full rounded-md border border-default bg-surface py-2 pl-9 pr-3 text-sm text-base-primary placeholder-base-muted focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        />
                    </div>
                </div>

                <ul
                    :id="listId"
                    ref="listbox"
                    role="listbox"
                    :aria-labelledby="buttonId"
                    :tabindex="isSearchable ? undefined : -1"
                    :aria-activedescendant="isSearchable ? undefined : activeId"
                    class="max-h-60 overflow-auto py-1 focus:outline-none"
                >
                    <template
                        v-for="(entry, index) in entries"
                        :key="`${String(entry.value)}-${index}`"
                    >
                        <li
                            v-if="entry.row?.dividerBefore"
                            role="presentation"
                            class="mx-3 my-1 border-t border-default"
                        />
                        <li
                            :id="optionId(index)"
                            role="option"
                            :aria-selected="isSelected(entry) ? 'true' : 'false'"
                            :aria-disabled="entry.disabled ? 'true' : undefined"
                            :aria-label="
                                entry.row?.parentLabel
                                    ? `${entry.row.parentLabel} › ${entry.label}`
                                    : undefined
                            "
                            :data-active="index === activeIndex ? '' : undefined"
                            class="relative select-none py-2 pr-10 transition-colors duration-150"
                            :class="[
                                entry.row?.level === 1 ? 'pl-10' : 'pl-4',
                                entry.disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                                isSelected(entry)
                                    ? 'bg-primary-100 dark:bg-primary-900/30'
                                    : index === activeIndex
                                      ? 'bg-primary-50 dark:bg-primary-900/20'
                                      : '',
                            ]"
                            @mousedown.prevent
                            @mousemove="hover(index)"
                            @click="choose(entry)"
                        >
                            <span
                                v-if="entry.row?.level === 1"
                                class="absolute inset-y-0 left-6 border-l border-default"
                                aria-hidden="true"
                            />
                            <span
                                class="block break-words"
                                :class="[
                                    entry.row === null
                                        ? 'text-base-muted'
                                        : isSelected(entry)
                                          ? 'font-semibold text-primary'
                                          : 'text-base-primary',
                                    entry.row?.hasChildren && !isSelected(entry)
                                        ? 'font-medium'
                                        : '',
                                ]"
                            >
                                {{ entry.label }}
                            </span>
                            <span
                                v-if="isSelected(entry)"
                                class="absolute inset-y-0 right-0 flex items-center pr-4 text-primary"
                            >
                                <Check class="h-5 w-5" aria-hidden="true" />
                            </span>
                        </li>
                    </template>
                    <li
                        v-if="entries.length === 0"
                        role="presentation"
                        class="px-4 py-3 text-sm text-base-muted"
                    >
                        {{ t('common.noResults') }}
                    </li>
                </ul>
            </div>
        </transition>
    </div>
</template>
