<script setup lang="ts">
import { RadioGroup, RadioGroupOption } from '@headlessui/vue';

export interface RadioOption {
    value: string;
    label: string;
    description?: string;
}

withDefaults(
    defineProps<{
        modelValue: string;
        options: RadioOption[];
        name: string;
        label?: string | undefined;
        error?: string | undefined;
        disabled?: boolean | undefined;
    }>(),
    {
        label: '',
        error: '',
        disabled: false,
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

function handleChange(value: string): void {
    emit('update:modelValue', value);
}
</script>

<template>
    <RadioGroup
        :model-value="modelValue"
        @update:model-value="handleChange"
        :disabled="disabled"
        :name="name"
    >
        <label v-if="label" class="block text-sm font-medium text-base-primary mb-2">
            {{ label }}
        </label>

        <div class="space-y-2">
            <RadioGroupOption
                v-for="option in options"
                :key="option.value"
                :value="option.value"
                v-slot="{ checked, active }"
                as="template"
            >
                <div
                    class="flex items-start gap-3 cursor-pointer group rounded-md p-2 hover:bg-muted transition-colors"
                    :class="{ 'opacity-50 cursor-not-allowed': disabled }"
                >
                    <!-- Radio visual custom -->
                    <div
                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border-2 flex items-center justify-center transition-all duration-150 border-neutral-300 dark:border-neutral-600 bg-surface group-hover:border-primary-400 dark:group-hover:border-primary-500 group-hover:shadow-sm"
                        :class="[
                            active
                                ? 'ring-2 ring-primary-500 ring-offset-2 ring-offset-surface'
                                : '',
                            checked ? 'border-primary-600 dark:border-primary-500' : '',
                        ]"
                    >
                        <!-- Inner dot -->
                        <div
                            class="w-2.5 h-2.5 rounded-full bg-primary-600 dark:bg-primary-500 transition-all duration-150"
                            :class="checked ? 'scale-100 opacity-100' : 'scale-0 opacity-0'"
                        />
                    </div>

                    <div class="flex-1 min-w-0">
                        <span class="text-sm text-base-primary select-none">
                            {{ option.label }}
                        </span>
                        <p v-if="option.description" class="text-xs text-base-muted mt-0.5">
                            {{ option.description }}
                        </p>
                    </div>
                </div>
            </RadioGroupOption>
        </div>

        <p v-if="error" class="mt-1 text-sm text-error">
            {{ error }}
        </p>
    </RadioGroup>
</template>
