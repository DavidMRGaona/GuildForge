<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
    Combobox,
    ComboboxInput,
    ComboboxButton,
    ComboboxOptions,
    ComboboxOption,
} from '@headlessui/vue';

interface Option {
    [key: string]: unknown;
}

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null;
        options: Option[];
        optionLabel: string;
        optionValue: string;
        placeholder?: string | undefined;
        searchable?: boolean | undefined;
        disabled?: boolean | undefined;
        error?: string | undefined;
        id?: string | undefined;
    }>(),
    {
        placeholder: '',
        searchable: true,
        disabled: false,
        error: '',
        id: '',
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null];
}>();

const query = ref('');

const selectedOption = computed(() => {
    if (props.modelValue === null || props.modelValue === '') return null;
    return props.options.find(
        (option) => option[props.optionValue] === props.modelValue
    ) ?? null;
});

const filteredOptions = computed(() => {
    if (!query.value || !props.searchable) return props.options;
    const normalizedQuery = query.value.toLowerCase().trim();
    return props.options.filter((option) =>
        String(option[props.optionLabel])
            .toLowerCase()
            .includes(normalizedQuery)
    );
});

const displayValue = computed(() => {
    if (!selectedOption.value) return '';
    return String(selectedOption.value[props.optionLabel]);
});

function handleChange(value: unknown): void {
    emit('update:modelValue', value as string | number | null);
}

// Reset query when selection changes
watch(() => props.modelValue, () => {
    query.value = '';
});
</script>

<template>
    <Combobox
        :model-value="modelValue"
        @update:model-value="handleChange"
        :disabled="disabled"
        as="div"
        class="relative"
    >
        <div class="relative">
            <ComboboxInput
                :id="id"
                class="w-full rounded-lg border bg-surface px-4 py-2.5 pr-10
                       text-base-primary placeholder-base-muted
                       transition-colors duration-150
                       focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                       disabled:bg-muted disabled:cursor-not-allowed"
                :class="[
                    error
                        ? 'border-error focus:ring-error focus:border-error'
                        : 'border-default hover:border-strong'
                ]"
                :display-value="() => displayValue"
                :placeholder="placeholder"
                @change="query = ($event.target as HTMLInputElement).value"
                autocomplete="off"
            />
            <ComboboxButton
                class="absolute inset-y-0 right-0 flex items-center pr-3"
                :class="{ 'cursor-not-allowed': disabled }"
            >
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
            </ComboboxButton>
        </div>

        <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <ComboboxOptions
                class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg
                       bg-surface border border-default shadow-lg
                       focus:outline-none"
            >
                <div
                    v-if="filteredOptions.length === 0 && query !== ''"
                    class="px-4 py-3 text-sm text-base-muted"
                >
                    No se encontraron resultados
                </div>

                <ComboboxOption
                    v-for="option in filteredOptions"
                    :key="String(option[optionValue])"
                    :value="option[optionValue] as string | number | null"
                    v-slot="{ active, selected }"
                    as="template"
                >
                    <li
                        class="relative cursor-pointer select-none px-4 py-2.5 transition-colors"
                        :class="[
                            active ? 'bg-primary-50 dark:bg-primary-900/20' : '',
                            selected ? 'bg-primary-100 dark:bg-primary-900/30' : ''
                        ]"
                    >
                        <span
                            class="block truncate"
                            :class="[
                                selected ? 'font-semibold text-primary-700 dark:text-primary-300' : 'text-base-primary'
                            ]"
                        >
                            {{ option[optionLabel] }}
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
                </ComboboxOption>
            </ComboboxOptions>
        </transition>
    </Combobox>
</template>
