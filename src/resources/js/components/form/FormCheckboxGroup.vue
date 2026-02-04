<script setup lang="ts">
import { computed } from 'vue';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';

export interface CheckboxOption {
    id: string;
    name: string;
    description?: string;
}

export interface CheckboxGroup {
    key: string;
    label: string;
    severity?: 'mild' | 'moderate' | 'severe';
    options: CheckboxOption[];
}

const props = withDefaults(
    defineProps<{
        modelValue: string[];
        groups: CheckboxGroup[];
        collapsible?: boolean | undefined;
        defaultOpen?: boolean | undefined;
        error?: string | undefined;
    }>(),
    {
        collapsible: true,
        defaultOpen: false,
        error: '',
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

function isChecked(optionId: string): boolean {
    return props.modelValue.includes(optionId);
}

function toggleOption(optionId: string): void {
    const newValue = isChecked(optionId)
        ? props.modelValue.filter((id) => id !== optionId)
        : [...props.modelValue, optionId];
    emit('update:modelValue', newValue);
}

function selectedCount(group: CheckboxGroup): number {
    return group.options.filter((opt) => props.modelValue.includes(opt.id)).length;
}

const severityColors = computed(() => ({
    mild: {
        bg: 'bg-success/10 dark:bg-success/20',
        border: 'border-success/30',
        text: 'text-success dark:text-success',
        badge: 'bg-success/20 text-success',
    },
    moderate: {
        bg: 'bg-warning/10 dark:bg-warning/20',
        border: 'border-warning/30',
        text: 'text-warning dark:text-warning',
        badge: 'bg-warning/20 text-warning',
    },
    severe: {
        bg: 'bg-error/10 dark:bg-error/20',
        border: 'border-error/30',
        text: 'text-error dark:text-error',
        badge: 'bg-error/20 text-error',
    },
}));

function getSeverityClasses(severity?: 'mild' | 'moderate' | 'severe'): {
    bg: string;
    border: string;
    text: string;
    badge: string;
} {
    if (!severity) {
        return {
            bg: 'bg-muted',
            border: 'border-default',
            text: 'text-base-primary',
            badge: 'bg-neutral-200 dark:bg-neutral-700 text-base-secondary',
        };
    }
    return severityColors.value[severity];
}
</script>

<template>
    <div class="space-y-3">
        <template v-if="collapsible">
            <Disclosure
                v-for="group in groups"
                :key="group.key"
                v-slot="{ open }"
                :default-open="defaultOpen"
                as="div"
                class="rounded-lg border overflow-hidden"
                :class="getSeverityClasses(group.severity).border"
            >
                <DisclosureButton
                    class="flex w-full items-center justify-between px-4 py-3 text-left transition-colors"
                    :class="[
                        getSeverityClasses(group.severity).bg,
                        'hover:brightness-95 dark:hover:brightness-110',
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <span class="font-medium text-base-primary">
                            {{ group.label }}
                        </span>
                        <span
                            v-if="group.severity"
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="getSeverityClasses(group.severity).badge"
                        >
                            {{
                                group.severity === 'mild'
                                    ? 'Leve'
                                    : group.severity === 'moderate'
                                      ? 'Moderado'
                                      : 'Severo'
                            }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-base-muted">
                            {{ selectedCount(group) }}/{{ group.options.length }}
                        </span>
                        <svg
                            class="h-5 w-5 text-base-muted transition-transform duration-200"
                            :class="{ 'rotate-180': open }"
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
                    </div>
                </DisclosureButton>

                <transition
                    enter-active-class="transition duration-100 ease-out"
                    enter-from-class="transform opacity-0"
                    enter-to-class="transform opacity-100"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="transform opacity-100"
                    leave-to-class="transform opacity-0"
                >
                    <DisclosurePanel class="bg-surface px-4 py-3">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <label
                                v-for="option in group.options"
                                :key="option.id"
                                class="flex items-start gap-3 cursor-pointer rounded-md p-2 hover:bg-muted transition-colors group/checkbox"
                            >
                                <!-- Hidden input for accessibility -->
                                <input
                                    type="checkbox"
                                    :checked="isChecked(option.id)"
                                    @change="toggleOption(option.id)"
                                    class="peer sr-only"
                                />

                                <!-- Custom checkbox visual -->
                                <div
                                    class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-2 flex items-center justify-center transition-all duration-150 peer-focus:ring-2 peer-focus:ring-primary-500 peer-focus:ring-offset-2"
                                    :class="[
                                        isChecked(option.id)
                                            ? 'bg-primary-600 border-primary-600 dark:bg-primary-500 dark:border-primary-500 group-hover/checkbox:bg-primary-700 dark:group-hover/checkbox:bg-primary-400'
                                            : 'bg-surface border-neutral-300 dark:border-neutral-600 group-hover/checkbox:border-primary-400 dark:group-hover/checkbox:border-primary-500 group-hover/checkbox:shadow-sm',
                                    ]"
                                >
                                    <!-- Checkmark with animation -->
                                    <svg
                                        class="h-3 w-3 transition-all duration-150"
                                        :class="
                                            isChecked(option.id)
                                                ? 'opacity-100 scale-100'
                                                : 'opacity-0 scale-0'
                                        "
                                        viewBox="0 0 12 12"
                                        fill="none"
                                    >
                                        <path
                                            d="M2 6l3 3 5-6"
                                            stroke="white"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <span class="text-sm text-base-primary">
                                        {{ option.name }}
                                    </span>
                                    <p
                                        v-if="option.description"
                                        class="text-xs text-base-muted mt-0.5"
                                    >
                                        {{ option.description }}
                                    </p>
                                </div>
                            </label>
                        </div>
                    </DisclosurePanel>
                </transition>
            </Disclosure>
        </template>

        <!-- Non-collapsible mode -->
        <template v-else>
            <div
                v-for="group in groups"
                :key="group.key"
                class="rounded-lg border p-4"
                :class="getSeverityClasses(group.severity).border"
            >
                <div class="flex items-center gap-3 mb-3">
                    <span class="font-medium text-base-primary">
                        {{ group.label }}
                    </span>
                    <span
                        v-if="group.severity"
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="getSeverityClasses(group.severity).badge"
                    >
                        {{
                            group.severity === 'mild'
                                ? 'Leve'
                                : group.severity === 'moderate'
                                  ? 'Moderado'
                                  : 'Severo'
                        }}
                    </span>
                    <span class="text-sm text-base-muted ml-auto">
                        {{ selectedCount(group) }}/{{ group.options.length }}
                    </span>
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <label
                        v-for="option in group.options"
                        :key="option.id"
                        class="flex items-start gap-3 cursor-pointer rounded-md p-2 hover:bg-muted transition-colors group/checkbox"
                    >
                        <!-- Hidden input for accessibility -->
                        <input
                            type="checkbox"
                            :checked="isChecked(option.id)"
                            @change="toggleOption(option.id)"
                            class="peer sr-only"
                        />

                        <!-- Custom checkbox visual -->
                        <div
                            class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-2 flex items-center justify-center transition-all duration-150 peer-focus:ring-2 peer-focus:ring-primary-500 peer-focus:ring-offset-2"
                            :class="[
                                isChecked(option.id)
                                    ? 'bg-primary-600 border-primary-600 dark:bg-primary-500 dark:border-primary-500 group-hover/checkbox:bg-primary-700 dark:group-hover/checkbox:bg-primary-400'
                                    : 'bg-surface border-neutral-300 dark:border-neutral-600 group-hover/checkbox:border-primary-400 dark:group-hover/checkbox:border-primary-500 group-hover/checkbox:shadow-sm',
                            ]"
                        >
                            <!-- Checkmark with animation -->
                            <svg
                                class="h-3 w-3 transition-all duration-150"
                                :class="
                                    isChecked(option.id)
                                        ? 'opacity-100 scale-100'
                                        : 'opacity-0 scale-0'
                                "
                                viewBox="0 0 12 12"
                                fill="none"
                            >
                                <path
                                    d="M2 6l3 3 5-6"
                                    stroke="white"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </div>

                        <div class="flex-1 min-w-0">
                            <span class="text-sm text-base-primary">
                                {{ option.name }}
                            </span>
                            <p v-if="option.description" class="text-xs text-base-muted mt-0.5">
                                {{ option.description }}
                            </p>
                        </div>
                    </label>
                </div>
            </div>
        </template>

        <p v-if="error" class="mt-1 text-sm text-error">
            {{ error }}
        </p>
    </div>
</template>
