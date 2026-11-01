<script setup lang="ts">
import { Clock } from '@lucide/vue';
import { computed, nextTick, ref, useAttrs, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { parseTimeField, timeOptions } from '@/utils/dateTimeFields';

/**
 * A time of day typed freely ('18:30', '9h30', '930') or picked from a list in steps of
 * `step` minutes. The value is 'HH:MM', '' when empty, or the text as typed while it is
 * not a time (the field then says how to write one). Attributes such as aria-label go to
 * the text field.
 */
defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        modelValue: string;
        id?: string | undefined;
        name?: string | undefined;
        placeholder?: string | undefined;
        disabled?: boolean | undefined;
        error?: string | undefined;
        /** Minutes between the times offered in the list. */
        step?: number | undefined;
    }>(),
    {
        id: undefined,
        name: undefined,
        placeholder: '',
        disabled: false,
        error: '',
        step: 30,
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const attrs = useAttrs();
const { t } = useI18n();
const uid = useId();
const inputId = computed(() => props.id ?? `${uid}-input`);
const listId = `${uid}-list`;
const invalidId = `${uid}-invalid`;

const text = ref(props.modelValue);
const touched = ref(false);
let lastEmitted: string | null = null;

watch(
    () => props.modelValue,
    (value) => {
        if (value !== lastEmitted) {
            text.value = value;
            touched.value = false;
        }
    }
);

const options = computed(() => timeOptions(props.step));
const isOpen = ref(false);
const activeIndex = ref(-1);

const invalid = computed(
    () => touched.value && text.value.trim() !== '' && parseTimeField(text.value) === null
);
const activeId = computed(() =>
    isOpen.value && activeIndex.value >= 0 ? `${uid}-option-${activeIndex.value}` : undefined
);
const ariaInvalid = computed(() =>
    props.error || invalid.value
        ? 'true'
        : attrs['aria-invalid'] === 'true' || attrs['aria-invalid'] === true
          ? 'true'
          : undefined
);
const describedBy = computed(
    () =>
        [attrs['aria-describedby'], invalid.value ? invalidId : null]
            .filter((id) => typeof id === 'string' && id !== '')
            .join(' ') || undefined
);

/** The option that matches the text, or the first one that starts like it. */
function matchingIndex(typed: string): number {
    const time = parseTimeField(typed);
    if (time !== null) {
        const exact = options.value.indexOf(time);
        if (exact !== -1) {
            return exact;
        }
    }
    const digits = typed.replace(/\D/g, '');
    if (digits === '') {
        return -1;
    }

    return options.value.findIndex((option) => {
        const packed = option.replace(':', '');

        return packed.startsWith(digits) || packed.startsWith(`0${digits}`);
    });
}

function showActive(): void {
    void nextTick(() => {
        const id = activeId.value;
        if (id !== undefined) {
            document.getElementById(id)?.scrollIntoView?.({ block: 'nearest' });
        }
    });
}

function open(): void {
    if (props.disabled) {
        return;
    }
    isOpen.value = true;
    activeIndex.value = matchingIndex(text.value);
    showActive();
}

function close(): void {
    isOpen.value = false;
    activeIndex.value = -1;
}

function setValue(value: string): void {
    lastEmitted = value;
    emit('update:modelValue', value);
}

function pick(option: string): void {
    text.value = option;
    touched.value = false;
    setValue(option);
    close();
}

function onInput(event: Event): void {
    text.value = (event.target as HTMLInputElement).value;
    setValue(parseTimeField(text.value) ?? text.value.trim());
    isOpen.value = true;
    activeIndex.value = matchingIndex(text.value);
    showActive();
}

function onBlur(): void {
    touched.value = true;
    close();
    const time = parseTimeField(text.value);
    if (time !== null) {
        text.value = time;
    }
}

function move(offset: number): void {
    if (!isOpen.value) {
        open();
        if (activeIndex.value === -1) {
            activeIndex.value = 0;
        }

        return;
    }
    const last = options.value.length - 1;
    activeIndex.value =
        activeIndex.value === -1
            ? offset > 0
                ? 0
                : last
            : Math.min(last, Math.max(0, activeIndex.value + offset));
    showActive();
}

function onKeydown(event: KeyboardEvent): void {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            move(1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            move(-1);
            break;
        case 'Enter': {
            const option = options.value[activeIndex.value];
            if (isOpen.value && option !== undefined) {
                event.preventDefault();
                pick(option);
            }
            break;
        }
        case 'Escape':
            if (isOpen.value) {
                event.stopPropagation();
                close();
            }
            break;
    }
}

function toggle(): void {
    if (isOpen.value) {
        close();
    } else {
        document.getElementById(inputId.value)?.focus();
        open();
    }
}
</script>

<template>
    <div class="relative">
        <div class="relative">
            <input
                :id="inputId"
                v-bind="attrs"
                type="text"
                role="combobox"
                inputmode="numeric"
                autocomplete="off"
                aria-autocomplete="list"
                :name="name"
                :value="text"
                :disabled="disabled"
                :placeholder="placeholder || t('common.timePicker.placeholder')"
                :aria-expanded="isOpen ? 'true' : 'false'"
                :aria-controls="listId"
                :aria-activedescendant="activeId"
                :aria-invalid="ariaInvalid"
                :aria-describedby="describedBy"
                class="w-full rounded-lg border bg-surface px-4 py-2.5 pr-12 text-base-primary tabular-nums placeholder:text-base-muted transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 disabled:bg-muted disabled:cursor-not-allowed"
                :class="
                    error || invalid
                        ? 'border-error focus:ring-error focus:border-error'
                        : 'border-default hover:border-strong'
                "
                @input="onInput"
                @blur="onBlur"
                @keydown="onKeydown"
                @click="open"
            />
            <button
                type="button"
                tabindex="-1"
                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-base-muted hover:text-base-primary disabled:cursor-not-allowed"
                :disabled="disabled"
                :aria-label="t('common.timePicker.open')"
                :aria-expanded="isOpen ? 'true' : 'false'"
                :aria-controls="listId"
                @mousedown.prevent
                @click="toggle"
            >
                <Clock class="h-5 w-5" aria-hidden="true" />
            </button>
        </div>

        <p v-if="invalid && !error" :id="invalidId" class="mt-1 text-sm text-error">
            {{ t('common.timePicker.invalid', { example: '18:30' }) }}
        </p>

        <ul
            v-show="isOpen"
            :id="listId"
            role="listbox"
            :aria-labelledby="inputId"
            class="absolute left-0 z-20 mt-1 max-h-60 w-full min-w-[8rem] overflow-auto rounded-lg border border-default bg-surface py-1 shadow-lg"
        >
            <template v-if="isOpen">
                <li
                    v-for="(option, index) in options"
                    :id="`${uid}-option-${index}`"
                    :key="option"
                    role="option"
                    :aria-selected="option === modelValue ? 'true' : 'false'"
                    class="cursor-pointer select-none px-4 py-2 tabular-nums transition-colors duration-150"
                    :class="[
                        option === modelValue ? 'font-semibold text-primary' : 'text-base-primary',
                        index === activeIndex
                            ? 'bg-primary-50 dark:bg-primary-900/20'
                            : 'hover:bg-neutral-200 dark:hover:bg-neutral-700',
                    ]"
                    @mousedown.prevent="pick(option)"
                >
                    {{ option }}
                </li>
            </template>
        </ul>
    </div>
</template>
