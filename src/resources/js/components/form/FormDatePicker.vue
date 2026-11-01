<script setup lang="ts">
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, useAttrs, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { venueDayKey } from '@/utils/datetime';
import {
    addDays,
    addMonths,
    dateFieldPattern,
    formatDateField,
    monthWeeks,
    parseDateField,
    weekdayOf,
} from '@/utils/dateTimeFields';

/**
 * A calendar day typed in the language's order or picked from a month. The value is
 * 'YYYY-MM-DD', '' when empty, or the text as typed while it is not a day (the field
 * then says how to write one). Attributes such as aria-label go to the text field.
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
        /** First day that may be picked, 'YYYY-MM-DD'. */
        min?: string | undefined;
        /** Last day that may be picked, 'YYYY-MM-DD'. */
        max?: string | undefined;
    }>(),
    {
        id: undefined,
        name: undefined,
        placeholder: '',
        disabled: false,
        error: '',
        min: undefined,
        max: undefined,
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const attrs = useAttrs();
const { t, locale } = useI18n();
const uid = useId();
const inputId = computed(() => props.id ?? `${uid}-input`);
const dialogId = `${uid}-dialog`;
const titleId = `${uid}-title`;
const invalidId = `${uid}-invalid`;

const text = ref(formatDateField(props.modelValue, locale.value));
const touched = ref(false);
let lastEmitted: string | null = null;

watch(
    () => props.modelValue,
    (value) => {
        if (value !== lastEmitted) {
            text.value = formatDateField(value, locale.value);
            touched.value = false;
        }
    }
);

const invalid = computed(
    () =>
        touched.value &&
        text.value.trim() !== '' &&
        parseDateField(text.value, locale.value) === null
);
const placeholderText = computed(() => {
    if (props.placeholder) {
        return props.placeholder;
    }
    const pattern = dateFieldPattern(locale.value);

    return pattern.order.map((part) => t(`common.datePicker.${part}`)).join(pattern.separator);
});
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

function onInput(event: Event): void {
    text.value = (event.target as HTMLInputElement).value;
    const value = parseDateField(text.value, locale.value) ?? text.value.trim();
    lastEmitted = value;
    emit('update:modelValue', value);
}

function onBlur(): void {
    touched.value = true;
    const day = parseDateField(text.value, locale.value);
    if (day !== null) {
        text.value = formatDateField(day, locale.value);
    }
}

// The month on screen and the day that holds the focus inside it
const isOpen = ref(false);
const focused = ref('');
const root = ref<HTMLElement | null>(null);
const toggleButton = ref<HTMLButtonElement | null>(null);
const input = ref<HTMLInputElement | null>(null);

const today = venueDayKey(new Date());
const visibleMonth = computed(() => focused.value.slice(0, 7));
const firstWeekday = computed(() => firstWeekdayOf(locale.value));
const weeks = computed(() => monthWeeks(visibleMonth.value, firstWeekday.value));
const monthLabel = computed(() =>
    capitalize(
        new Intl.DateTimeFormat(locale.value, {
            month: 'long',
            year: 'numeric',
            timeZone: 'UTC',
        }).format(new Date(`${visibleMonth.value}-01T00:00:00Z`))
    )
);
const weekdays = computed(() =>
    (weeks.value[0] ?? []).map((day) => ({
        long: weekdayName(day, 'long'),
        narrow: weekdayName(day, 'narrow'),
    }))
);

function weekdayName(day: string, width: 'long' | 'narrow'): string {
    return new Intl.DateTimeFormat(locale.value, { weekday: width, timeZone: 'UTC' }).format(
        new Date(`${day}T00:00:00Z`)
    );
}

function dayLabel(day: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'full',
        timeZone: 'UTC',
    }).format(new Date(`${day}T00:00:00Z`));
}

function outOfRange(day: string): boolean {
    return (
        (props.min !== undefined && day < props.min) || (props.max !== undefined && day > props.max)
    );
}

function clamp(day: string): string {
    if (props.min !== undefined && day < props.min) {
        return props.min;
    }
    if (props.max !== undefined && day > props.max) {
        return props.max;
    }

    return day;
}

function open(): void {
    if (props.disabled) {
        return;
    }
    focused.value = clamp(parseDateField(text.value, locale.value) ?? today);
    isOpen.value = true;
    focusDay();
}

function close(returnFocus: boolean): void {
    isOpen.value = false;
    if (returnFocus) {
        toggleButton.value?.focus();
    }
}

function toggle(): void {
    if (isOpen.value) {
        close(false);
    } else {
        open();
    }
}

function pick(day: string): void {
    if (outOfRange(day)) {
        return;
    }
    text.value = formatDateField(day, locale.value);
    touched.value = false;
    lastEmitted = day;
    emit('update:modelValue', day);
    isOpen.value = false;
    input.value?.focus();
}

function focusDay(): void {
    void nextTick(() => {
        root.value?.querySelector<HTMLButtonElement>(`[data-day="${focused.value}"]`)?.focus();
    });
}

function moveTo(day: string): void {
    focused.value = clamp(day);
    focusDay();
}

function showMonth(months: number): void {
    const target = addMonths(visibleMonth.value, months);
    const day = Number(focused.value.slice(8, 10));
    const lastDay = new Date(Date.UTC(Number(target.slice(0, 4)), Number(target.slice(5, 7)), 0));
    focused.value = clamp(
        `${target}-${String(Math.min(day, lastDay.getUTCDate())).padStart(2, '0')}`
    );
}

function onDayKeydown(event: KeyboardEvent): void {
    const weekday = (weekdayOf(focused.value) - firstWeekday.value + 7) % 7;
    const moves: Record<string, () => string> = {
        ArrowLeft: () => addDays(focused.value, -1),
        ArrowRight: () => addDays(focused.value, 1),
        ArrowUp: () => addDays(focused.value, -7),
        ArrowDown: () => addDays(focused.value, 7),
        Home: () => addDays(focused.value, -weekday),
        End: () => addDays(focused.value, 6 - weekday),
    };
    const move = moves[event.key];
    if (move !== undefined) {
        event.preventDefault();
        moveTo(move());

        return;
    }
    if (event.key === 'PageUp' || event.key === 'PageDown') {
        event.preventDefault();
        showMonth((event.key === 'PageUp' ? -1 : 1) * (event.shiftKey ? 12 : 1));
        focusDay();
    }
}

// Escape closes the month without leaving an enclosing dialog
function onEscape(event: KeyboardEvent): void {
    if (isOpen.value) {
        event.stopPropagation();
        close(true);
    }
}

function onPointerDown(event: PointerEvent): void {
    if (isOpen.value && !root.value?.contains(event.target as Node)) {
        close(false);
    }
}

watch(isOpen, (value) => {
    if (value) {
        document.addEventListener('pointerdown', onPointerDown);
    } else {
        document.removeEventListener('pointerdown', onPointerDown);
    }
});

onBeforeUnmount(() => document.removeEventListener('pointerdown', onPointerDown));

function firstWeekdayOf(tag: string): number {
    try {
        const info = new Intl.Locale(tag) as Intl.Locale & {
            getWeekInfo?: () => { firstDay: number };
            weekInfo?: { firstDay: number };
        };
        const firstDay = info.getWeekInfo?.().firstDay ?? info.weekInfo?.firstDay;
        if (firstDay !== undefined) {
            return firstDay % 7;
        }
    } catch {
        // An unknown tag falls back to Monday below
    }

    return tag.toLowerCase() === 'en' || tag.toLowerCase().startsWith('en-us') ? 0 : 1;
}

function capitalize(value: string): string {
    return value.charAt(0).toLocaleUpperCase(locale.value) + value.slice(1);
}

const example = computed(() => formatDateField('2026-12-31', locale.value));
</script>

<template>
    <div ref="root" class="relative" @keydown.esc="onEscape">
        <div class="relative">
            <input
                :id="inputId"
                ref="input"
                v-bind="attrs"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                :name="name"
                :value="text"
                :disabled="disabled"
                :placeholder="placeholderText"
                :aria-invalid="ariaInvalid"
                :aria-describedby="describedBy"
                class="w-full rounded-lg border bg-surface px-4 py-2.5 pr-12 text-base-primary placeholder:text-base-muted transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 disabled:bg-muted disabled:cursor-not-allowed"
                :class="
                    error || invalid
                        ? 'border-error focus:ring-error focus:border-error'
                        : 'border-default hover:border-strong'
                "
                @input="onInput"
                @blur="onBlur"
                @keydown.alt.down.prevent="open"
            />
            <button
                ref="toggleButton"
                type="button"
                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-base-muted hover:text-base-primary focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed"
                :disabled="disabled"
                :aria-label="t('common.datePicker.open')"
                aria-haspopup="dialog"
                :aria-expanded="isOpen ? 'true' : 'false'"
                :aria-controls="isOpen ? dialogId : undefined"
                @click="toggle"
            >
                <CalendarDays class="h-5 w-5" aria-hidden="true" />
            </button>
        </div>

        <p v-if="invalid && !error" :id="invalidId" class="mt-1 text-sm text-error">
            {{ t('common.datePicker.invalid', { example }) }}
        </p>

        <div
            v-if="isOpen"
            :id="dialogId"
            role="dialog"
            :aria-labelledby="titleId"
            class="absolute left-0 z-20 mt-1 w-[19rem] max-w-[calc(100vw-2rem)] rounded-lg border border-default bg-surface p-3 shadow-lg"
        >
            <div class="mb-2 flex items-center justify-between gap-2">
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-md text-base-secondary hover:bg-muted hover:text-base-primary focus:outline-none focus:ring-2 focus:ring-primary-500"
                    :aria-label="t('common.datePicker.previousMonth')"
                    @click="showMonth(-1)"
                >
                    <ChevronLeft class="h-5 w-5" aria-hidden="true" />
                </button>
                <p :id="titleId" class="font-semibold text-base-primary" aria-live="polite">
                    {{ monthLabel }}
                </p>
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-md text-base-secondary hover:bg-muted hover:text-base-primary focus:outline-none focus:ring-2 focus:ring-primary-500"
                    :aria-label="t('common.datePicker.nextMonth')"
                    @click="showMonth(1)"
                >
                    <ChevronRight class="h-5 w-5" aria-hidden="true" />
                </button>
            </div>

            <table role="grid" :aria-labelledby="titleId" class="w-full border-collapse text-sm">
                <thead>
                    <tr>
                        <th
                            v-for="weekday in weekdays"
                            :key="weekday.long"
                            scope="col"
                            :abbr="weekday.long"
                            class="pb-1 text-xs font-medium text-base-muted"
                        >
                            <span aria-hidden="true">{{ weekday.narrow }}</span>
                            <span class="sr-only">{{ weekday.long }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="week in weeks" :key="week[0]">
                        <td
                            v-for="day in week"
                            :key="day"
                            class="p-0.5 text-center"
                            :aria-selected="day === modelValue ? 'true' : 'false'"
                        >
                            <button
                                type="button"
                                :data-day="day"
                                :tabindex="day === focused ? 0 : -1"
                                :disabled="outOfRange(day)"
                                :aria-label="dayLabel(day)"
                                :aria-current="day === today ? 'date' : undefined"
                                class="flex h-10 w-full items-center justify-center rounded-md tabular-nums transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-40"
                                :class="[
                                    day === modelValue
                                        ? 'bg-primary font-semibold text-white hover:bg-primary-700'
                                        : day.slice(0, 7) === visibleMonth
                                          ? 'text-base-primary hover:bg-muted'
                                          : 'text-base-muted hover:bg-muted',
                                    day === today && day !== modelValue
                                        ? 'font-semibold ring-1 ring-inset ring-primary-500'
                                        : '',
                                ]"
                                @click="pick(day)"
                                @keydown="onDayKeydown"
                            >
                                {{ Number(day.slice(8, 10)) }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
