<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        modelValue: string[];
        placeholder?: string;
        maxTags?: number;
        maxLength?: number;
        disabled?: boolean;
        error?: string | undefined;
        id?: string;
        name?: string;
    }>(),
    {
        placeholder: '',
        maxTags: 10,
        maxLength: 200,
        disabled: false,
        error: undefined,
        id: '',
        name: '',
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const { t } = useI18n();

const inputValue = ref('');
const inputRef = ref<HTMLInputElement | null>(null);

const canAddMore = computed(() => props.modelValue.length < props.maxTags);

function addTag(): void {
    const trimmed = inputValue.value.trim();
    if (!trimmed || !canAddMore.value || props.disabled) return;

    // Check max length
    if (trimmed.length > props.maxLength) return;

    // Check for duplicates (case-insensitive)
    const isDuplicate = props.modelValue.some((tag) => tag.toLowerCase() === trimmed.toLowerCase());
    if (isDuplicate) {
        inputValue.value = '';
        return;
    }

    emit('update:modelValue', [...props.modelValue, trimmed]);
    inputValue.value = '';
}

function removeTag(index: number): void {
    if (props.disabled) return;
    const newTags = [...props.modelValue];
    newTags.splice(index, 1);
    emit('update:modelValue', newTags);
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter') {
        event.preventDefault();
        addTag();
    } else if (
        event.key === 'Backspace' &&
        inputValue.value === '' &&
        props.modelValue.length > 0
    ) {
        removeTag(props.modelValue.length - 1);
    }
}

function focusInput(): void {
    inputRef.value?.focus();
}
</script>

<template>
    <div>
        <div
            class="flex flex-wrap items-center gap-2 rounded-lg border bg-surface px-3 py-2 transition-colors duration-150 focus-within:ring-2 focus-within:ring-primary-500 focus-within:border-primary-500"
            :class="[
                error
                    ? 'border-error focus-within:ring-error focus-within:border-error'
                    : 'border-default hover:border-strong',
                disabled ? 'bg-muted cursor-not-allowed' : 'cursor-text',
            ]"
            @click="focusInput"
        >
            <!-- Existing tags -->
            <span
                v-for="(tag, index) in modelValue"
                :key="index"
                class="inline-flex items-center gap-1 rounded-md bg-primary-100 dark:bg-primary-900/30 px-2.5 py-1 text-sm font-medium text-primary-800 dark:text-primary-200"
            >
                <span class="max-w-[200px] truncate">{{ tag }}</span>
                <button
                    v-if="!disabled"
                    type="button"
                    class="ml-0.5 inline-flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full text-primary hover:bg-primary-200 dark:hover:bg-primary-800 hover:text-primary-800 dark:hover:text-primary-100 focus:outline-none focus:bg-primary-500 focus:text-white transition-colors duration-150"
                    :aria-label="t('common.removeTag', { tag })"
                    @click.stop="removeTag(index)"
                >
                    <X class="h-3 w-3" aria-hidden="true" />
                </button>
            </span>

            <!-- Input field -->
            <input
                v-if="canAddMore && !disabled"
                :id="id"
                ref="inputRef"
                v-model="inputValue"
                type="text"
                :name="name"
                :placeholder="modelValue.length === 0 ? placeholder : ''"
                :maxlength="maxLength"
                class="flex-1 min-w-[120px] border-0 bg-transparent p-0 py-1 text-base-primary placeholder-base-muted focus:outline-none focus:ring-0"
                @keydown="handleKeydown"
            />

            <!-- Add button -->
            <button
                v-if="canAddMore && !disabled && inputValue.trim()"
                type="button"
                :aria-label="t('common.addTag')"
                class="inline-flex items-center justify-center rounded-md bg-primary hover:bg-primary-600 px-2 py-1 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors duration-150"
                @click="addTag"
            >
                <Plus class="h-4 w-4" aria-hidden="true" />
            </button>
        </div>

        <!-- Helper text showing remaining tags -->
        <p v-if="!error && modelValue.length > 0" class="mt-1 text-xs text-base-muted">
            {{ modelValue.length }}/{{ maxTags }}
        </p>
    </div>
</template>
