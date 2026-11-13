<script setup lang="ts">
import { computed, useId } from 'vue';

const props = defineProps<{
    label: string;
    /** Id of the control inside, so the label points at it. */
    for?: string | undefined;
    error?: string | undefined;
    hint?: string | undefined;
    required?: boolean | undefined;
}>();

const uid = useId();
const hintId = computed(() => `${uid}-hint`);
const errorId = computed(() => `${uid}-error`);
</script>

<template>
    <div>
        <label :for="props.for" class="block text-sm font-medium text-base-secondary">
            {{ label }}
            <span v-if="required" class="text-error" aria-hidden="true">*</span>
        </label>
        <p v-if="hint" :id="hintId" class="mt-1 text-sm text-base-muted">{{ hint }}</p>
        <div class="mt-1">
            <slot :hint-id="hint ? hintId : undefined" :error-id="error ? errorId : undefined" />
        </div>
        <p v-if="error" :id="errorId" role="alert" class="mt-1 text-sm text-error">{{ error }}</p>
    </div>
</template>
