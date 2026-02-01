<script setup lang="ts">
import {
    Listbox,
    ListboxButton,
    ListboxOptions,
    ListboxOption,
} from '@headlessui/vue';

interface Option {
    value: string | number;
    label: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null;
        options: Option[];
        placeholder?: string;
        disabled?: boolean;
        error?: string;
        id?: string;
    }>(),
    {
        placeholder: '',
        disabled: false,
        error: '',
        id: '',
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null];
}>();

function getSelectedLabel(): string {
    if (props.modelValue === null || props.modelValue === '') {
        return props.placeholder;
    }
    const selected = props.options.find((opt) => opt.value === props.modelValue);
    return selected?.label ?? props.placeholder;
}

function handleChange(value: string | number | null): void {
    emit('update:modelValue', value);
}
</script>

<template>
    <Listbox
        :model-value="modelValue"
        @update:model-value="handleChange"
        :disabled="disabled"
        as="div"
        class="relative"
    >
        <ListboxButton
            :id="id"
            class="relative w-full rounded-lg border bg-surface px-4 py-2.5 pr-10
                   text-left transition-colors duration-150
                   focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                   disabled:bg-muted disabled:cursor-not-allowed"
            :class="[
                error
                    ? 'border-error focus:ring-error focus:border-error'
                    : 'border-default hover:border-strong',
                modelValue === null || modelValue === ''
                    ? 'text-base-muted'
                    : 'text-base-primary'
            ]"
        >
            <span class="block truncate">
                {{ getSelectedLabel() }}
            </span>
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                <svg
                    class="h-5 w-5 text-base-muted transition-transform ui-open:rotate-180"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M19 9l-7 7-7-7"
                    />
                </svg>
            </span>
        </ListboxButton>

        <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <ListboxOptions
                class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg
                       bg-surface border border-default shadow-lg
                       focus:outline-none"
            >
                <!-- Placeholder option to allow clearing -->
                <ListboxOption
                    v-if="placeholder"
                    :value="''"
                    v-slot="{ active }"
                    as="template"
                >
                    <li
                        class="relative cursor-pointer select-none px-4 py-2.5 text-base-muted
                               transition-colors duration-150
                               hover:bg-neutral-100 dark:hover:bg-neutral-800"
                        :class="{ 'bg-primary-50 dark:bg-primary-900/20': active }"
                    >
                        {{ placeholder }}
                    </li>
                </ListboxOption>

                <ListboxOption
                    v-for="option in options"
                    :key="String(option.value)"
                    :value="option.value"
                    v-slot="{ active, selected }"
                    as="template"
                >
                    <li
                        class="relative cursor-pointer select-none px-4 py-2.5
                               transition-colors duration-150"
                        :class="[
                            selected
                                ? 'bg-primary-100 dark:bg-primary-900/30 hover:bg-primary-200 dark:hover:bg-primary-800/40'
                                : active
                                    ? 'bg-primary-50 dark:bg-primary-900/20'
                                    : 'hover:bg-neutral-100 dark:hover:bg-neutral-800'
                        ]"
                    >
                        <span
                            class="block truncate"
                            :class="[
                                selected ? 'font-semibold text-primary-700 dark:text-primary-300' : 'text-base-primary'
                            ]"
                        >
                            {{ option.label }}
                        </span>
                        <span
                            v-if="selected"
                            class="absolute inset-y-0 right-0 flex items-center pr-4 text-primary-600 dark:text-primary-400"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path
                                    fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </span>
                    </li>
                </ListboxOption>
            </ListboxOptions>
        </transition>
    </Listbox>
</template>
